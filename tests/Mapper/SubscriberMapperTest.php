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
    // deleteForThread
    // -------------------------------------------------------------------------

    public function testDeleteForThreadRemovesAllSubscribersForThatThread(): void
    {
        $mapper = $this->makeMapper();
        $mapper->subscribe(1, 40, 100, SubscriberMapper::SUB_MESSAGE);
        $mapper->subscribe(2, 40, 100, SubscriberMapper::SUB_BOOKMARK);
        $mapper->subscribe(3, 40, 200, SubscriberMapper::SUB_MESSAGE); // different thread

        $mapper->deleteForThread(40, 100);

        $this->assertNull($mapper->getSubscription(1, 40, 100));
        $this->assertNull($mapper->getSubscription(2, 40, 100));
        $this->assertSame(SubscriberMapper::SUB_MESSAGE, $mapper->getSubscription(3, 40, 200));
    }

    public function testDeleteForThreadScopesToForum(): void
    {
        $mapper = $this->makeMapper();
        $mapper->subscribe(1, 41, 100, SubscriberMapper::SUB_MESSAGE);

        $mapper->deleteForThread(999, 100); // wrong forum

        $this->assertSame(SubscriberMapper::SUB_MESSAGE, $mapper->getSubscription(1, 41, 100));
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

    public function testListEmailSubscribersReportsMatchedThreadForThreadSpecificSubscriber(): void
    {
        $uid = $this->insert('phorum_users', [
            'username' => 'threaduser', 'email' => 'thread@example.com', 'active' => 1, 'settings_data' => '{}',
        ]);
        $this->insert('phorum_subscribers', [
            'user_id' => $uid, 'forum_id' => 33, 'thread' => 42, 'sub_type' => SubscriberMapper::SUB_MESSAGE,
        ]);

        $mapper  = $this->makeMapper();
        $results = $mapper->listEmailSubscribers(33, 42, 999);
        $this->assertCount(1, $results);
        $this->assertSame(42, (int) $results[0]['matched_thread']);
    }

    public function testListEmailSubscribersReportsZeroMatchedThreadForForumWideSubscriber(): void
    {
        $uid = $this->insert('phorum_users', [
            'username' => 'forumuser', 'email' => 'forum@example.com', 'active' => 1, 'settings_data' => '{}',
        ]);
        $this->insert('phorum_subscribers', [
            'user_id' => $uid, 'forum_id' => 34, 'thread' => 0, 'sub_type' => SubscriberMapper::SUB_MESSAGE,
        ]);

        $mapper  = $this->makeMapper();
        $results = $mapper->listEmailSubscribers(34, 99, 999);
        $this->assertCount(1, $results);
        $this->assertSame(0, (int) $results[0]['matched_thread']);
    }

    public function testListEmailSubscribersDedupesUserWithBothThreadAndForumWideSubscription(): void
    {
        $uid = $this->insert('phorum_users', [
            'username' => 'bothuser', 'email' => 'both@example.com', 'active' => 1, 'settings_data' => '{}',
        ]);
        $this->insert('phorum_subscribers', [
            'user_id' => $uid, 'forum_id' => 35, 'thread' => 55, 'sub_type' => SubscriberMapper::SUB_MESSAGE,
        ]);
        $this->insert('phorum_subscribers', [
            'user_id' => $uid, 'forum_id' => 35, 'thread' => 0, 'sub_type' => SubscriberMapper::SUB_MESSAGE,
        ]);

        $mapper  = $this->makeMapper();
        $results = $mapper->listEmailSubscribers(35, 55, 999);
        $this->assertCount(1, $results);
        // Forum-wide (0) wins so the unsubscribe link points at the broader subscription.
        $this->assertSame(0, (int) $results[0]['matched_thread']);
    }
}
