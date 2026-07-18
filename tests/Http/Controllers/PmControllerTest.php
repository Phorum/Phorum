<?php
declare(strict_types=1);

namespace Phorum\Tests\Http\Controllers;

use Phorum\Core\Auth;
use Phorum\Http\Controllers\PmController;
use Phorum\Http\Request;
use Phorum\Mapper\PmBuddyMapper;
use Phorum\Mapper\UserMapper;
use Phorum\Model\MessageMeta;
use Phorum\Model\PmFolder;
use Phorum\Model\PmMessage;
use Phorum\Model\PmXref;
use Phorum\Service\PmService;
use Phorum\Tests\Http\ControllerTestCase;
use Twig\Environment;
use Twig\Loader\LoaderInterface;

class PmControllerTest extends ControllerTestCase
{
    private function makeController(array $deps = []): PmController
    {
        return new PmController(
            config:    $this->makeConfig(),
            twig:      $deps['twig'] ?? $this->makeTwig(),
            pmService: $deps['pmService'] ?? $this->createMock(PmService::class),
            users:     $deps['users']     ?? $this->createMock(UserMapper::class),
            buddies:   $deps['buddies']   ?? $this->createMock(PmBuddyMapper::class),
        );
    }

    private function makeFolder(int $id = 1, string $name = 'My Folder'): PmFolder
    {
        $f               = new PmFolder();
        $f->pm_folder_id = $id;
        $f->foldername   = $name;
        return $f;
    }

    // -------------------------------------------------------------------------
    // inbox — login guard
    // -------------------------------------------------------------------------

    public function testInboxRedirectsAnonymousUser(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->inbox(new Request(server: ['REQUEST_URI' => '/pm']));
        $this->assertSame(302, $response->status);
        $this->assertStringContainsString('/login', $response->headers['Location']);
    }

    public function testInboxReturns200WhenLoggedIn(): void
    {
        Auth::setUser($this->makeUser());
        $pmService = $this->createMock(PmService::class);
        $pmService->method('listFolder')->willReturn([]);
        $pmService->method('listFolders')->willReturn([]);

        $ctrl     = $this->makeController(['pmService' => $pmService]);
        $response = $ctrl->inbox(new Request(server: ['REQUEST_URI' => '/pm']));
        $this->assertSame(200, $response->status);
    }

    // -------------------------------------------------------------------------
    // outbox
    // -------------------------------------------------------------------------

    public function testOutboxRedirectsAnonymousUser(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->outbox(new Request(server: ['REQUEST_URI' => '/pm/outbox']));
        $this->assertSame(302, $response->status);
    }

    public function testOutboxReturns200WhenLoggedIn(): void
    {
        Auth::setUser($this->makeUser());
        $pmService = $this->createMock(PmService::class);
        $pmService->method('listFolder')->willReturn([]);
        $pmService->method('listFolders')->willReturn([]);

        $ctrl     = $this->makeController(['pmService' => $pmService]);
        $response = $ctrl->outbox(new Request(server: ['REQUEST_URI' => '/pm/outbox']));
        $this->assertSame(200, $response->status);
    }

    // -------------------------------------------------------------------------
    // folder
    // -------------------------------------------------------------------------

    public function testFolderRedirectsAnonymousUser(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->folder(new Request(
            server: ['REQUEST_URI' => '/pm/folder/1'],
            tokens: ['pm_folder_id' => '1'],
        ));
        $this->assertSame(302, $response->status);
    }

    public function testFolderReturns404WhenNotOwner(): void
    {
        Auth::setUser($this->makeUser());
        $pmService = $this->createMock(PmService::class);
        $pmService->method('listFolders')->willReturn([]); // user has no folders

        $ctrl     = $this->makeController(['pmService' => $pmService]);
        $response = $ctrl->folder(new Request(
            server: ['REQUEST_URI' => '/pm/folder/99'],
            tokens: ['pm_folder_id' => '99'],
        ));
        $this->assertSame(404, $response->status);
    }

    public function testFolderReturns200ForOwnedFolder(): void
    {
        Auth::setUser($this->makeUser());
        $folder    = $this->makeFolder(5, 'Archive');
        $pmService = $this->createMock(PmService::class);
        $pmService->method('listFolders')->willReturn([$folder]);
        $pmService->method('listCustomFolder')->willReturn([]);

        $ctrl     = $this->makeController(['pmService' => $pmService]);
        $response = $ctrl->folder(new Request(
            server: ['REQUEST_URI' => '/pm/folder/5'],
            tokens: ['pm_folder_id' => '5'],
        ));
        $this->assertSame(200, $response->status);
    }

    // -------------------------------------------------------------------------
    // compose
    // -------------------------------------------------------------------------

    public function testComposeRedirectsAnonymousUser(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->compose(new Request(server: ['REQUEST_URI' => '/pm/compose']));
        $this->assertSame(302, $response->status);
    }

    public function testComposeGetFormReturns200(): void
    {
        Auth::setUser($this->makeUser());
        $pmService = $this->createMock(PmService::class);

        $ctrl     = $this->makeController(['pmService' => $pmService]);
        $response = $ctrl->compose($this->makeGetRequest(server: ['REQUEST_URI' => '/pm/compose']));
        $this->assertSame(200, $response->status);
    }

    public function testComposePostValidationErrorForEmptyRecipient(): void
    {
        Auth::setUser($this->makeUser());
        $ctrl     = $this->makeController();
        $response = $ctrl->compose($this->makePostRequest(
            post:   ['to_username' => '', 'subject' => 'Hello', 'body' => 'World'],
            server: ['REQUEST_URI' => '/pm/compose'],
        ));
        $this->assertSame(200, $response->status);
    }

    public function testComposePostValidationErrorForUnknownRecipient(): void
    {
        Auth::setUser($this->makeUser());
        $users = $this->createMock(UserMapper::class);
        $users->method('findByUsername')->willReturn(null);

        $ctrl     = $this->makeController(['users' => $users]);
        $response = $ctrl->compose($this->makePostRequest(
            post:   ['to_username' => 'nobody', 'subject' => 'Hi', 'body' => 'Hey'],
            server: ['REQUEST_URI' => '/pm/compose'],
        ));
        $this->assertSame(200, $response->status);
    }

    public function testComposePostSuccessRedirects(): void
    {
        $sender = $this->makeUser(1);
        Auth::setUser($sender);

        $recipient = $this->makeUser(2);
        $users     = $this->createMock(UserMapper::class);
        $users->method('findByUsername')->willReturn($recipient);

        $pmService = $this->createMock(PmService::class);
        $pmService->expects($this->once())->method('send');

        $ctrl     = $this->makeController(['users' => $users, 'pmService' => $pmService]);
        $response = $ctrl->compose($this->makePostRequest(
            post:   ['to_username' => 'user2', 'subject' => 'Hello', 'body' => 'World'],
            server: ['REQUEST_URI' => '/pm/compose'],
        ));
        $this->assertSame(302, $response->status);
        $this->assertSame('/pm', $response->headers['Location']);
    }

    public function testComposePreviewShowsRenderedMessageWithoutSending(): void
    {
        $sender = $this->makeUser(1);
        Auth::setUser($sender);

        $recipient = $this->makeUser(2);
        $users     = $this->createMock(UserMapper::class);
        $users->method('findByUsername')->willReturn($recipient);

        $pmService = $this->createMock(PmService::class);
        $pmService->expects($this->never())->method('send');

        $twig = $this->makeTwig();
        $twig->expects($this->once())->method('render')->with(
            'pm/compose.html.twig',
            $this->callback(function (array $ctx): bool {
                return $ctx['show_preview'] === true
                    && $ctx['preview_msg'] instanceof PmMessage
                    && $ctx['preview_msg']->subject === 'Hello'
                    && $ctx['preview_msg']->message === 'World'
                    && $ctx['preview_sender'] instanceof \Phorum\Model\User
                    && $ctx['preview_recipients'] === [['user_id' => 2, 'username' => 'user2']];
            })
        );

        $ctrl     = $this->makeController(['users' => $users, 'pmService' => $pmService, 'twig' => $twig]);
        $response = $ctrl->compose($this->makePostRequest(
            post:   ['action' => 'preview', 'to_username' => 'user2', 'subject' => 'Hello', 'body' => 'World'],
            server: ['REQUEST_URI' => '/pm/compose'],
        ));
        $this->assertSame(200, $response->status);
    }

    public function testComposePreviewStillValidatesRequiredFields(): void
    {
        Auth::setUser($this->makeUser());

        $pmService = $this->createMock(PmService::class);
        $pmService->expects($this->never())->method('send');

        $twig = $this->makeTwig();
        $twig->expects($this->once())->method('render')->with(
            'pm/compose.html.twig',
            $this->callback(function (array $ctx): bool {
                return $ctx['show_preview'] === false
                    && in_array('Message body is required.', $ctx['errors'], true);
            })
        );

        $ctrl     = $this->makeController(['pmService' => $pmService, 'twig' => $twig]);
        $response = $ctrl->compose($this->makePostRequest(
            post:   ['action' => 'preview', 'to_username' => '', 'subject' => 'Hello', 'body' => ''],
            server: ['REQUEST_URI' => '/pm/compose'],
        ));
        $this->assertSame(200, $response->status);
    }

    // -------------------------------------------------------------------------
    // read
    // -------------------------------------------------------------------------

    public function testReadRedirectsAnonymousUser(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->read(new Request(
            server: ['REQUEST_URI' => '/pm/read/1'],
            tokens: ['pm_xref_id' => '1'],
        ));
        $this->assertSame(302, $response->status);
    }

    public function testReadReturns404WhenNotFound(): void
    {
        Auth::setUser($this->makeUser());
        $pmService = $this->createMock(PmService::class);
        $pmService->method('getMessage')->willReturn(null);

        $ctrl     = $this->makeController(['pmService' => $pmService]);
        $response = $ctrl->read(new Request(
            server: ['REQUEST_URI' => '/pm/read/99'],
            tokens: ['pm_xref_id' => '99'],
        ));
        $this->assertSame(404, $response->status);
    }

    public function testReadPassesRecipientsDecodedFromMeta(): void
    {
        Auth::setUser($this->makeUser());

        $xref                 = new PmXref();
        $xref->pm_xref_id     = 5;
        $xref->pm_message_id  = 10;

        $msg                = new PmMessage();
        $msg->pm_message_id = 10;
        $msg->user_id       = 2;
        $msg->meta          = MessageMeta::fromArray([
            'recipients' => [['user_id' => 1, 'username' => 'brianlmoon']],
            'format'     => 'markdown',
        ])->encode();

        $pmService = $this->createMock(PmService::class);
        $pmService->method('getMessage')->willReturn(['xref' => $xref, 'message' => $msg]);
        $pmService->method('listFolders')->willReturn([]);

        $users = $this->createMock(UserMapper::class);
        $users->method('load')->willReturn(null);

        $twig = $this->createMock(Environment::class);
        $twig->method('getLoader')->willReturn($this->createMock(LoaderInterface::class));
        $twig->expects($this->once())->method('render')->with(
            'pm/read.html.twig',
            $this->callback(fn(array $data) => ($data['recipients'] ?? null)
                === [['user_id' => 1, 'username' => 'brianlmoon']]),
        )->willReturn('<html>ok</html>');

        $ctrl = new PmController(
            config:    $this->makeConfig(),
            twig:      $twig,
            pmService: $pmService,
            users:     $users,
            buddies:   $this->createMock(PmBuddyMapper::class),
        );

        $response = $ctrl->read(new Request(
            server: ['REQUEST_URI' => '/pm/read/5'],
            tokens: ['pm_xref_id' => '5'],
        ));
        $this->assertSame(200, $response->status);
    }

    // -------------------------------------------------------------------------
    // delete
    // -------------------------------------------------------------------------

    public function testDeleteRedirectsAnonymousUser(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->delete(new Request(
            server: ['REQUEST_URI' => '/pm/delete/1'],
            tokens: ['pm_xref_id' => '1'],
        ));
        $this->assertSame(302, $response->status);
    }

    public function testDeleteGetReturns404WhenNotFound(): void
    {
        Auth::setUser($this->makeUser());
        $pmService = $this->createMock(PmService::class);
        $pmService->method('getMessage')->willReturn(null);

        $ctrl     = $this->makeController(['pmService' => $pmService]);
        $response = $ctrl->delete($this->makeGetRequest(
            server: ['REQUEST_URI' => '/pm/delete/99'],
            tokens: ['pm_xref_id' => '99'],
        ));
        $this->assertSame(404, $response->status);
    }

    public function testDeletePostCallsServiceAndRedirects(): void
    {
        Auth::setUser($this->makeUser());
        $pmService = $this->createMock(PmService::class);
        $pmService->expects($this->once())->method('delete')->with(7, 1);

        $ctrl     = $this->makeController(['pmService' => $pmService]);
        $response = $ctrl->delete($this->makePostRequest(
            server: ['REQUEST_URI' => '/pm/delete/7'],
            tokens: ['pm_xref_id' => '7'],
        ));
        $this->assertSame(302, $response->status);
        $this->assertSame('/pm', $response->headers['Location']);
    }

    // -------------------------------------------------------------------------
    // folders
    // -------------------------------------------------------------------------

    public function testFoldersRedirectsAnonymousUser(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->folders(new Request(server: ['REQUEST_URI' => '/pm/folders']));
        $this->assertSame(302, $response->status);
    }

    public function testFoldersPostCreatesAndRedirects(): void
    {
        Auth::setUser($this->makeUser());
        $pmService = $this->createMock(PmService::class);
        $pmService->expects($this->once())->method('createFolder')->with(1, 'Archive');

        $ctrl     = $this->makeController(['pmService' => $pmService]);
        $response = $ctrl->folders($this->makePostRequest(
            post:   ['foldername' => 'Archive'],
            server: ['REQUEST_URI' => '/pm/folders'],
        ));
        $this->assertSame(302, $response->status);
        $this->assertSame('/pm/folders', $response->headers['Location']);
    }

    public function testFoldersPostValidationErrorForEmptyName(): void
    {
        Auth::setUser($this->makeUser());
        $pmService = $this->createMock(PmService::class);
        $pmService->method('listFolders')->willReturn([]);
        $pmService->expects($this->never())->method('createFolder');

        $ctrl     = $this->makeController(['pmService' => $pmService]);
        $response = $ctrl->folders($this->makePostRequest(
            post:   ['foldername' => ''],
            server: ['REQUEST_URI' => '/pm/folders'],
        ));
        $this->assertSame(200, $response->status);
    }
}
