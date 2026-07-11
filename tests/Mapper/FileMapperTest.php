<?php
declare(strict_types=1);

namespace Phorum\Tests\Mapper;

use DealNews\DB\CRUD;
use Phorum\Mapper\FileMapper;
use Phorum\Model\File;

class FileMapperTest extends MapperTestCase
{
    private function makeMapper(): FileMapper
    {
        return new class extends FileMapper {
            protected function crud(): CRUD
            {
                return MapperTestCase::$crud;
            }
        };
    }

    private function seedFile(array $override = []): int
    {
        return $this->insert('phorum_files', array_merge([
            'user_id'      => 1,
            'filename'     => 'test.txt',
            'filesize'     => 100,
            'file_data'    => '',
            'add_datetime' => 1000,
            'message_id'   => 0,
            'link'         => File::LINK_MESSAGE,
        ], $override));
    }

    // -------------------------------------------------------------------------
    // CRUD
    // -------------------------------------------------------------------------

    public function testSaveInsertAndLoad(): void
    {
        $mapper = $this->makeMapper();
        $f = new File();
        $f->user_id      = 1;
        $f->filename     = 'photo.jpg';
        $f->filesize     = 1024;
        $f->add_datetime = 5000;
        $f->link         = File::LINK_MESSAGE;
        $mapper->save($f);

        $this->assertGreaterThan(0, $f->file_id);
        $loaded = $mapper->load($f->file_id);
        $this->assertSame('photo.jpg', $loaded->filename);
    }

    public function testDelete(): void
    {
        $id = $this->seedFile();
        $mapper = $this->makeMapper();
        $mapper->delete($id);
        $this->assertNull($mapper->load($id));
    }

    // -------------------------------------------------------------------------
    // findByMessage
    // -------------------------------------------------------------------------

    public function testFindByMessageReturnsAttachments(): void
    {
        $this->seedFile(['message_id' => 5, 'link' => File::LINK_MESSAGE, 'filename' => 'a.txt']);
        $this->seedFile(['message_id' => 5, 'link' => File::LINK_MESSAGE, 'filename' => 'b.txt']);
        $this->seedFile(['message_id' => 6, 'link' => File::LINK_MESSAGE, 'filename' => 'c.txt']);
        // editor link should not be returned
        $this->seedFile(['message_id' => 5, 'link' => File::LINK_EDITOR, 'filename' => 'd.txt']);

        $mapper  = $this->makeMapper();
        $results = $mapper->findByMessage(5);
        $this->assertCount(2, $results);
        $names = array_column($results, 'filename');
        $this->assertContains('a.txt', $names);
        $this->assertContains('b.txt', $names);
    }

    public function testFindByMessageReturnsEmptyForNoAttachments(): void
    {
        $mapper = $this->makeMapper();
        $this->assertSame([], $mapper->findByMessage(99));
    }

    // -------------------------------------------------------------------------
    // findByMessages
    // -------------------------------------------------------------------------

    public function testFindByMessages(): void
    {
        $this->seedFile(['message_id' => 10, 'link' => File::LINK_MESSAGE, 'filename' => 'x.txt']);
        $this->seedFile(['message_id' => 11, 'link' => File::LINK_MESSAGE, 'filename' => 'y.txt']);

        $mapper = $this->makeMapper();
        $result = $mapper->findByMessages([10, 11]);
        $this->assertArrayHasKey(10, $result);
        $this->assertArrayHasKey(11, $result);
        $this->assertSame('x.txt', $result[10][0]->filename);
    }

    public function testFindByMessagesReturnsEmptyForEmptyInput(): void
    {
        $mapper = $this->makeMapper();
        $this->assertSame([], $mapper->findByMessages([]));
    }

    // -------------------------------------------------------------------------
    // setLink
    // -------------------------------------------------------------------------

    public function testSetLink(): void
    {
        $id = $this->seedFile(['message_id' => 0, 'link' => File::LINK_EDITOR]);
        $mapper = $this->makeMapper();
        $mapper->setLink($id, 55, File::LINK_MESSAGE);

        $f = $mapper->load($id);
        $this->assertSame(55, $f->message_id);
        $this->assertSame(File::LINK_MESSAGE, $f->link);
    }

    // -------------------------------------------------------------------------
    // deleteByMessage
    // -------------------------------------------------------------------------

    public function testDeleteByMessage(): void
    {
        $this->seedFile(['message_id' => 20, 'link' => File::LINK_MESSAGE]);
        $this->seedFile(['message_id' => 20, 'link' => File::LINK_MESSAGE]);

        $mapper = $this->makeMapper();
        $mapper->deleteByMessage(20);

        $cnt = self::$pdo->query("SELECT COUNT(*) AS c FROM phorum_files WHERE message_id = 20")->fetch()['c'];
        $this->assertSame(0, (int) $cnt);
    }

    // -------------------------------------------------------------------------
    // findStaleEditorFiles
    // -------------------------------------------------------------------------

    public function testFindStaleEditorFiles(): void
    {
        $this->seedFile(['add_datetime' => 500,  'link' => File::LINK_EDITOR]); // stale
        $this->seedFile(['add_datetime' => 2000, 'link' => File::LINK_EDITOR]); // fresh
        $this->seedFile(['add_datetime' => 500,  'link' => File::LINK_MESSAGE]); // wrong link

        $mapper  = $this->makeMapper();
        $results = $mapper->findStaleEditorFiles(1000);
        $this->assertCount(1, $results);
        $this->assertSame(500, $results[0]->add_datetime);
    }
}
