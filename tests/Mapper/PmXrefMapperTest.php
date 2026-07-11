<?php
declare(strict_types=1);

namespace Phorum\Tests\Mapper;

use DealNews\DB\CRUD;
use Phorum\Mapper\PmXrefMapper;
use Phorum\Model\PmXref;

class PmXrefMapperTest extends MapperTestCase
{
    private function makeMapper(): PmXrefMapper
    {
        return new class extends PmXrefMapper {
            protected function crud(): CRUD
            {
                return MapperTestCase::$crud;
            }
        };
    }

    private function seedPmMessage(array $override = []): int
    {
        return $this->insert('phorum_pm_messages', array_merge([
            'user_id'   => 1,
            'author'    => 'alice',
            'subject'   => 'Test',
            'message'   => 'Hi',
            'datestamp' => 1000,
            'meta'      => '',
        ], $override));
    }

    private function seedXref(array $override = []): int
    {
        return $this->insert('phorum_pm_xref', array_merge([
            'user_id'        => 1,
            'pm_message_id'  => 1,
            'pm_folder_id'   => 0,
            'special_folder' => 'inbox',
            'read_flag'      => 0,
            'reply_flag'     => 0,
        ], $override));
    }

    // -------------------------------------------------------------------------
    // CRUD
    // -------------------------------------------------------------------------

    public function testSaveInsertAndLoad(): void
    {
        $pmId   = $this->seedPmMessage();
        $mapper = $this->makeMapper();
        $x = new PmXref();
        $x->user_id        = 1;
        $x->pm_message_id  = $pmId;
        $x->special_folder = 'inbox';
        $mapper->save($x);

        $this->assertGreaterThan(0, $x->pm_xref_id);
        $loaded = $mapper->load($x->pm_xref_id);
        $this->assertSame('inbox', $loaded->special_folder);
    }

    // -------------------------------------------------------------------------
    // listBySpecialFolder
    // -------------------------------------------------------------------------

    public function testListBySpecialFolder(): void
    {
        $pmId = $this->seedPmMessage(['author' => 'bob', 'subject' => 'Hello', 'datestamp' => 999]);
        $this->seedXref(['user_id' => 5, 'pm_message_id' => $pmId, 'special_folder' => 'inbox']);

        $mapper  = $this->makeMapper();
        $results = $mapper->listBySpecialFolder(5, 'inbox');
        $this->assertCount(1, $results);
        $this->assertSame('Hello', $results[0]['subject']);
        $this->assertSame('bob',   $results[0]['author']);
    }

    public function testListBySpecialFolderExcludesOtherFolder(): void
    {
        $pmId = $this->seedPmMessage();
        $this->seedXref(['user_id' => 5, 'pm_message_id' => $pmId, 'special_folder' => 'outbox']);

        $mapper  = $this->makeMapper();
        $results = $mapper->listBySpecialFolder(5, 'inbox');
        $this->assertSame([], $results);
    }

    // -------------------------------------------------------------------------
    // listByCustomFolder
    // -------------------------------------------------------------------------

    public function testListByCustomFolder(): void
    {
        $pmId = $this->seedPmMessage(['subject' => 'Custom', 'datestamp' => 500]);
        $this->seedXref(['user_id' => 6, 'pm_message_id' => $pmId, 'pm_folder_id' => 3, 'special_folder' => '']);

        $mapper  = $this->makeMapper();
        $results = $mapper->listByCustomFolder(6, 3);
        $this->assertCount(1, $results);
        $this->assertSame('Custom', $results[0]['subject']);
    }

    // -------------------------------------------------------------------------
    // findForUser
    // -------------------------------------------------------------------------

    public function testFindForUserReturnsXref(): void
    {
        $pmId = $this->seedPmMessage();
        $xid  = $this->seedXref(['user_id' => 7, 'pm_message_id' => $pmId]);

        $mapper = $this->makeMapper();
        $result = $mapper->findForUser($xid, 7);
        $this->assertNotNull($result);
        $this->assertSame(7, $result->user_id);
    }

    public function testFindForUserReturnsNullForWrongUser(): void
    {
        $pmId = $this->seedPmMessage();
        $xid  = $this->seedXref(['user_id' => 8, 'pm_message_id' => $pmId]);

        $mapper = $this->makeMapper();
        $this->assertNull($mapper->findForUser($xid, 99));
    }

    // -------------------------------------------------------------------------
    // markRead
    // -------------------------------------------------------------------------

    public function testMarkRead(): void
    {
        $pmId = $this->seedPmMessage();
        $xid  = $this->seedXref(['read_flag' => 0, 'pm_message_id' => $pmId]);

        $mapper = $this->makeMapper();
        $mapper->markRead($xid);

        $row = self::$pdo->query("SELECT read_flag FROM phorum_pm_xref WHERE pm_xref_id = {$xid}")->fetch();
        $this->assertSame(1, (int) $row['read_flag']);
    }

    // -------------------------------------------------------------------------
    // moveToFolder
    // -------------------------------------------------------------------------

    public function testMoveToFolder(): void
    {
        $pmId = $this->seedPmMessage();
        $xid  = $this->seedXref(['pm_folder_id' => 0, 'special_folder' => 'inbox', 'pm_message_id' => $pmId]);

        $mapper = $this->makeMapper();
        $mapper->moveToFolder($xid, 7);

        $x = $mapper->load($xid);
        $this->assertSame(7, $x->pm_folder_id);
        $this->assertSame('', $x->special_folder);
    }

    // -------------------------------------------------------------------------
    // moveAllToInbox
    // -------------------------------------------------------------------------

    public function testMoveAllToInbox(): void
    {
        $pm1 = $this->seedPmMessage();
        $pm2 = $this->seedPmMessage();
        $x1 = $this->seedXref(['user_id' => 9, 'pm_folder_id' => 4, 'special_folder' => '', 'pm_message_id' => $pm1]);
        $x2 = $this->seedXref(['user_id' => 9, 'pm_folder_id' => 4, 'special_folder' => '', 'pm_message_id' => $pm2]);

        $mapper = $this->makeMapper();
        $mapper->moveAllToInbox(9, 4);

        foreach ([$x1, $x2] as $xid) {
            $x = $mapper->load($xid);
            $this->assertSame(0, $x->pm_folder_id);
            $this->assertSame('inbox', $x->special_folder);
        }
    }
}
