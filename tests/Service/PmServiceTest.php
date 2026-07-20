<?php
declare(strict_types=1);

namespace Phorum\Tests\Service;

use Phorum\Core\Config;
use Phorum\Core\SiteSettings;
use Phorum\Hook\HookDispatcher;
use Phorum\Mapper\PmFolderMapper;
use Phorum\Mapper\PmMessageMapper;
use Phorum\Mapper\PmXrefMapper;
use Phorum\Mapper\SettingMapper;
use Phorum\Mapper\UserMapper;
use Phorum\Model\MessageMeta;
use Phorum\Model\PmFolder;
use Phorum\Model\PmMessage;
use Phorum\Model\PmXref;
use Phorum\Model\User;
use Phorum\Service\MailService;
use Phorum\Service\PmService;
use PHPUnit\Framework\TestCase;

class PmServiceTest extends TestCase
{
    protected function setUp(): void
    {
        HookDispatcher::reset();
        require_once dirname(__DIR__, 2) . '/src/Hook/functions.php';
    }

    protected function tearDown(): void
    {
        HookDispatcher::reset();
        SiteSettings::clear();
    }

    private function makeService(
        ?PmMessageMapper $messages = null,
        ?PmXrefMapper    $xrefs    = null,
        ?PmFolderMapper  $folders  = null,
        ?UserMapper      $users    = null,
        ?MailService     $mailer   = null,
        ?Config          $config   = null,
    ): PmService {
        $config ??= $this->createConfigMock(['base_url' => 'http://example.com']);
        return new PmService(
            $messages ?? $this->createMock(PmMessageMapper::class),
            $xrefs    ?? $this->createMock(PmXrefMapper::class),
            $folders  ?? $this->createMock(PmFolderMapper::class),
            $users    ?? $this->createMock(UserMapper::class),
            $mailer   ?? $this->createMock(MailService::class),
            $config,
        );
    }

    private function createConfigMock(array $values): Config
    {
        $config = $this->createMock(Config::class);
        $config->method('get')->willReturnCallback(fn($key, $default = null) => $values[$key] ?? $default);
        return $config;
    }

    private function makeUser(int $id, bool $pmEmailNotify = false): User
    {
        $u                  = new User();
        $u->user_id         = $id;
        $u->username        = 'user' . $id;
        $u->display_name    = '';
        $u->email           = "user{$id}@example.com";
        $u->pm_email_notify = $pmEmailNotify ? 1 : 0;
        return $u;
    }

    // -------------------------------------------------------------------------
    // send()
    // -------------------------------------------------------------------------

    public function testSendCreatesMsgAndXrefs(): void
    {
        $recipient = $this->makeUser(2);

        $messages = $this->createMock(PmMessageMapper::class);
        $messages->method('save')->willReturnCallback(function (PmMessage $m) {
            $m->pm_message_id = 99;
            return $m;
        });

        $xrefs = $this->createMock(PmXrefMapper::class);
        $xrefs->expects($this->exactly(2))->method('save'); // inbox + outbox

        $users = $this->createMock(UserMapper::class);
        $users->method('load')->willReturn($recipient);
        $users->expects($this->once())->method('incrementNewPmCount')->with(2);

        $svc = $this->makeService(messages: $messages, xrefs: $xrefs, users: $users);
        $msg = $svc->send(1, 'alice', [2], 'Hello', 'Body text');

        $this->assertInstanceOf(PmMessage::class, $msg);
        $this->assertSame(99, $msg->pm_message_id);

        $meta = MessageMeta::decode($msg->meta);
        $this->assertSame('markdown', $meta->format());
        $this->assertSame([['user_id' => 2, 'username' => 'user2']], $meta->get('recipients', []));
    }

    public function testSendSkipsNonExistentRecipients(): void
    {
        $messages = $this->createMock(PmMessageMapper::class);
        $messages->method('save')->willReturnCallback(function (PmMessage $m) {
            $m->pm_message_id = 1;
            return $m;
        });

        $xrefs = $this->createMock(PmXrefMapper::class);
        $xrefs->expects($this->once())->method('save'); // outbox only

        $users = $this->createMock(UserMapper::class);
        $users->method('load')->willReturn(null); // recipient not found

        $svc = $this->makeService(messages: $messages, xrefs: $xrefs, users: $users);
        $svc->send(1, 'alice', [999], 'Hi', 'Body');
    }

    public function testSendEmailsRecipientWhenNotifyEnabled(): void
    {
        $recipient = $this->makeUser(2, pmEmailNotify: true);

        $messages = $this->createMock(PmMessageMapper::class);
        $messages->method('save')->willReturnCallback(function (PmMessage $m) {
            $m->pm_message_id = 1;
            return $m;
        });

        $xrefs  = $this->createMock(PmXrefMapper::class);
        $users  = $this->createMock(UserMapper::class);
        $users->method('load')->willReturn($recipient);

        $mailer = $this->createMock(MailService::class);
        $mailer->expects($this->once())->method('send')
            ->with('user2@example.com', 'user2', $this->stringContains('New private message'), $this->anything());

        $svc = $this->makeService(messages: $messages, xrefs: $xrefs, users: $users, mailer: $mailer);
        $svc->send(1, 'alice', [2], 'Test', 'Body');
    }

    public function testSendSubjectIncludesSiteName(): void
    {
        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->willReturnMap([['site_name', 'My Test Forum']]);
        SiteSettings::initialize($settings, 'Phorum');

        $recipient = $this->makeUser(2, pmEmailNotify: true);

        $messages = $this->createMock(PmMessageMapper::class);
        $messages->method('save')->willReturnCallback(function (PmMessage $m) {
            $m->pm_message_id = 1;
            return $m;
        });

        $xrefs = $this->createMock(PmXrefMapper::class);
        $users = $this->createMock(UserMapper::class);
        $users->method('load')->willReturn($recipient);

        $mailer = $this->createMock(MailService::class);
        $mailer->expects($this->once())->method('send')
            ->with($this->anything(), $this->anything(), $this->stringContains('[My Test Forum]'), $this->anything());

        $svc = $this->makeService(messages: $messages, xrefs: $xrefs, users: $users, mailer: $mailer);
        $svc->send(1, 'alice', [2], 'Test', 'Body');
    }

    // -------------------------------------------------------------------------
    // listFolder / listCustomFolder
    // -------------------------------------------------------------------------

    public function testListFolderDelegatesToXrefMapper(): void
    {
        $rows  = [['pm_xref_id' => 1, 'subject' => 'Hi']];
        $xrefs = $this->createMock(PmXrefMapper::class);
        $xrefs->method('listBySpecialFolder')->with(1, 'inbox')->willReturn($rows);

        $svc    = $this->makeService(xrefs: $xrefs);
        $result = $svc->listFolder(1, 'inbox');
        $this->assertSame($rows, $result);
    }

    public function testListCustomFolderDelegatesToXrefMapper(): void
    {
        $rows  = [['pm_xref_id' => 2, 'subject' => 'Hey']];
        $xrefs = $this->createMock(PmXrefMapper::class);
        $xrefs->method('listByCustomFolder')->with(1, 5)->willReturn($rows);

        $svc    = $this->makeService(xrefs: $xrefs);
        $result = $svc->listCustomFolder(1, 5);
        $this->assertSame($rows, $result);
    }

    // -------------------------------------------------------------------------
    // getMessage()
    // -------------------------------------------------------------------------

    public function testGetMessageReturnsNullForWrongUser(): void
    {
        $xrefs = $this->createMock(PmXrefMapper::class);
        $xrefs->method('findForUser')->willReturn(null);

        $svc = $this->makeService(xrefs: $xrefs);
        $this->assertNull($svc->getMessage(1, 99));
    }

    public function testGetMessageMarksReadAndDecrementsIfUnread(): void
    {
        $xref = new PmXref();
        $xref->pm_xref_id    = 7;
        $xref->pm_message_id = 3;
        $xref->read_flag     = 0;

        $msg = new PmMessage();
        $msg->pm_message_id = 3;

        $xrefs = $this->createMock(PmXrefMapper::class);
        $xrefs->method('findForUser')->willReturn($xref);
        $xrefs->expects($this->once())->method('markRead')->with(7);

        $messages = $this->createMock(PmMessageMapper::class);
        $messages->method('load')->with(3)->willReturn($msg);

        $users = $this->createMock(UserMapper::class);
        $users->expects($this->once())->method('decrementNewPmCount');

        $svc    = $this->makeService(messages: $messages, xrefs: $xrefs, users: $users);
        $result = $svc->getMessage(7, 1);
        $this->assertSame($xref, $result['xref']);
        $this->assertSame($msg,  $result['message']);
    }

    public function testGetMessageDoesNotDecrementIfAlreadyRead(): void
    {
        $xref = new PmXref();
        $xref->pm_xref_id    = 8;
        $xref->pm_message_id = 4;
        $xref->read_flag     = 1;

        $msg = new PmMessage();
        $msg->pm_message_id = 4;

        $xrefs = $this->createMock(PmXrefMapper::class);
        $xrefs->method('findForUser')->willReturn($xref);
        $xrefs->expects($this->never())->method('markRead');

        $messages = $this->createMock(PmMessageMapper::class);
        $messages->method('load')->willReturn($msg);

        $users = $this->createMock(UserMapper::class);
        $users->expects($this->never())->method('decrementNewPmCount');

        $svc = $this->makeService(messages: $messages, xrefs: $xrefs, users: $users);
        $svc->getMessage(8, 1);
    }

    // -------------------------------------------------------------------------
    // delete()
    // -------------------------------------------------------------------------

    public function testDeleteReturnsFalseForWrongUser(): void
    {
        $xrefs = $this->createMock(PmXrefMapper::class);
        $xrefs->method('findForUser')->willReturn(null);

        $svc = $this->makeService(xrefs: $xrefs);
        $this->assertFalse($svc->delete(1, 99));
    }

    public function testDeleteDecrementsCountForUnreadMessage(): void
    {
        $xref = new PmXref();
        $xref->pm_xref_id = 10;
        $xref->read_flag  = 0;

        $xrefs = $this->createMock(PmXrefMapper::class);
        $xrefs->method('findForUser')->willReturn($xref);
        $xrefs->method('delete')->willReturn(true);

        $users = $this->createMock(UserMapper::class);
        $users->expects($this->once())->method('decrementNewPmCount');

        $svc = $this->makeService(xrefs: $xrefs, users: $users);
        $this->assertTrue($svc->delete(10, 1));
    }

    // -------------------------------------------------------------------------
    // move()
    // -------------------------------------------------------------------------

    public function testMoveReturnsFalseForWrongUser(): void
    {
        $xrefs = $this->createMock(PmXrefMapper::class);
        $xrefs->method('findForUser')->willReturn(null);

        $svc = $this->makeService(xrefs: $xrefs);
        $this->assertFalse($svc->move(1, 99, 5));
    }

    public function testMoveReturnsFalseForFolderOfDifferentUser(): void
    {
        $xref = new PmXref();
        $xref->pm_xref_id = 1;

        $folder = new PmFolder();
        $folder->user_id = 99; // different user

        $xrefs   = $this->createMock(PmXrefMapper::class);
        $xrefs->method('findForUser')->willReturn($xref);

        $folders = $this->createMock(PmFolderMapper::class);
        $folders->method('load')->willReturn($folder);

        $svc = $this->makeService(xrefs: $xrefs, folders: $folders);
        $this->assertFalse($svc->move(1, 1, 5));
    }

    public function testMoveCallsMoveToFolder(): void
    {
        $xref = new PmXref(); $xref->pm_xref_id = 3;
        $folder = new PmFolder(); $folder->pm_folder_id = 5; $folder->user_id = 1;

        $xrefs   = $this->createMock(PmXrefMapper::class);
        $xrefs->method('findForUser')->willReturn($xref);
        $xrefs->expects($this->once())->method('moveToFolder')->with(3, 5);

        $folders = $this->createMock(PmFolderMapper::class);
        $folders->method('load')->willReturn($folder);

        $svc = $this->makeService(xrefs: $xrefs, folders: $folders);
        $this->assertTrue($svc->move(3, 1, 5));
    }

    // -------------------------------------------------------------------------
    // folder management
    // -------------------------------------------------------------------------

    public function testListFoldersDelegatesToMapper(): void
    {
        $f = new PmFolder();
        $folders = $this->createMock(PmFolderMapper::class);
        $folders->method('findByUser')->with(1)->willReturn([$f]);

        $svc = $this->makeService(folders: $folders);
        $this->assertSame([$f], $svc->listFolders(1));
    }

    public function testCreateFolderSavesAndReturnsFolder(): void
    {
        $folders = $this->createMock(PmFolderMapper::class);
        $folders->method('save')->willReturnCallback(function (PmFolder $f) {
            $f->pm_folder_id = 42;
            return $f;
        });

        $svc    = $this->makeService(folders: $folders);
        $result = $svc->createFolder(1, 'My Folder');
        $this->assertSame(42, $result->pm_folder_id);
        $this->assertSame('My Folder', $result->foldername);
    }

    public function testDeleteFolderReturnsFalseForWrongUser(): void
    {
        $folder = new PmFolder();
        $folder->pm_folder_id = 5;
        $folder->user_id      = 99;

        $folders = $this->createMock(PmFolderMapper::class);
        $folders->method('load')->willReturn($folder);

        $svc = $this->makeService(folders: $folders);
        $this->assertFalse($svc->deleteFolder(5, 1));
    }

    public function testDeleteFolderMovesMessagesToInboxAndDeletes(): void
    {
        $folder = new PmFolder();
        $folder->pm_folder_id = 7;
        $folder->user_id      = 1;

        $folders = $this->createMock(PmFolderMapper::class);
        $folders->method('load')->willReturn($folder);
        $folders->method('delete')->willReturn(true);

        $xrefs = $this->createMock(PmXrefMapper::class);
        $xrefs->expects($this->once())->method('moveAllToInbox')->with(1, 7);

        $svc = $this->makeService(xrefs: $xrefs, folders: $folders);
        $this->assertTrue($svc->deleteFolder(7, 1));
    }
}
