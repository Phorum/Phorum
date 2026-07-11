<?php
declare(strict_types=1);

namespace Phorum\Tests\Mapper;

use DealNews\DB\CRUD;
use Phorum\Mapper\SubscriberMapper;

class SubscriberMapperTest extends MapperTestCase
{
    private function makeMapper(): SubscriberMapper
    {
        return new class extends SubscriberMapper {
            protected function crud(): CRUD
            {
                return MapperTestCase::$crud;
            }
        };
    }

    // -------------------------------------------------------------------------
    // subscribe / getSubscription
    // -------------------------------------------------------------------------

    public function testSubscribeInsertsRow(): void
    {
        $mapper = $this->makeMapper();
        $mapper->subscribe(1, 10, 0, SubscriberMapper::SUB_MESSAGE);

        $this->assertSame(SubscriberMapper::SUB_MESSAGE, $mapper->getSubscription(1, 10, 0));
    }

    public function testSubscribeUpdatesExistingRow(): void
    {
        $mapper = $this->makeMapper();
        $mapper->subscribe(1, 10, 0, SubscriberMapper::SUB_MESSAGE);
        $mapper->subscribe(1, 10, 0, SubscriberMapper::SUB_BOOKMARK);

        $this->assertSame(SubscriberMapper::SUB_BOOKMARK, $mapper->getSubscription(1, 10, 0));
    }

    public function testGetSubscriptionReturnsNullWhenNotFound(): void
    {
        $mapper = $this->makeMapper();
        $this->assertNull($mapper->getSubscription(9, 9, 9));
    }

    // -------------------------------------------------------------------------
    // unsubscribe
    // -------------------------------------------------------------------------

    public function testUnsubscribeRemovesRow(): void
    {
        $mapper = $this->makeMapper();
        $mapper->subscribe(2, 20, 5, SubscriberMapper::SUB_MESSAGE);
        $mapper->unsubscribe(2, 20, 5);

        $this->assertNull($mapper->getSubscription(2, 20, 5));
    }

    // -------------------------------------------------------------------------
    // listEmailSubscribers
    // -------------------------------------------------------------------------

    public function testListEmailSubscribersReturnsActiveSubscribers(): void
    {
        $uid = $this->insert('phorum_users', [
            'username'     => 'subuser',
            'email'        => 'sub@example.com',
            'active'       => 1,
            'settings_data' => '{}',
        ]);
        $this->insert('phorum_subscribers', [
            'user_id'  => $uid,
            'forum_id' => 30,
            'thread'   => 0,
            'sub_type' => SubscriberMapper::SUB_MESSAGE,
        ]);

        $mapper = $this->makeMapper();
        $results = $mapper->listEmailSubscribers(30, 0, 999);
        $this->assertCount(1, $results);
        $this->assertSame('sub@example.com', $results[0]['email']);
    }

    public function testListEmailSubscribersExcludesPostAuthor(): void
    {
        $uid = $this->insert('phorum_users', [
            'username'     => 'author',
            'email'        => 'author@example.com',
            'active'       => 1,
            'settings_data' => '{}',
        ]);
        $this->insert('phorum_subscribers', [
            'user_id'  => $uid,
            'forum_id' => 31,
            'thread'   => 0,
            'sub_type' => SubscriberMapper::SUB_MESSAGE,
        ]);

        $mapper  = $this->makeMapper();
        $results = $mapper->listEmailSubscribers(31, 0, $uid);
        $this->assertSame([], $results);
    }

    public function testListEmailSubscribersExcludesBookmarkType(): void
    {
        $uid = $this->insert('phorum_users', [
            'username'     => 'bkuser',
            'email'        => 'bk@example.com',
            'active'       => 1,
            'settings_data' => '{}',
        ]);
        $this->insert('phorum_subscribers', [
            'user_id'  => $uid,
            'forum_id' => 32,
            'thread'   => 0,
            'sub_type' => SubscriberMapper::SUB_BOOKMARK,
        ]);

        $mapper  = $this->makeMapper();
        $results = $mapper->listEmailSubscribers(32, 0, 999);
        $this->assertSame([], $results);
    }
}
