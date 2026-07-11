<?php
declare(strict_types=1);

namespace Phorum\Tests\Service;

use Phorum\Mapper\NewflagMapper;
use Phorum\Service\NewflagService;
use PHPUnit\Framework\TestCase;

class NewflagServiceTest extends TestCase
{
    // -------------------------------------------------------------------------
    // markRead()
    // -------------------------------------------------------------------------

    public function testMarkReadDoesNothingForGuestUser(): void
    {
        $mapper = $this->createMock(NewflagMapper::class);
        $mapper->expects($this->never())->method('getMinId');
        (new NewflagService($mapper))->markRead(0, 1, [10, 20]);
    }

    public function testMarkReadDoesNothingForEmptyIds(): void
    {
        $mapper = $this->createMock(NewflagMapper::class);
        $mapper->expects($this->never())->method('getMinId');
        (new NewflagService($mapper))->markRead(1, 1, []);
    }

    public function testMarkReadFiltersIdsAtOrBelowMinId(): void
    {
        $mapper = $this->createMock(NewflagMapper::class);
        $mapper->method('getMinId')->willReturn(100);
        $mapper->method('countFlags')->willReturn(0);
        $mapper->expects($this->once())->method('addFlags')
            ->with(1, 1, [101, 200]);

        (new NewflagService($mapper))->markRead(1, 1, [99, 100, 101, 200]);
    }

    public function testMarkReadDoesNothingWhenAllIdsAtOrBelowMin(): void
    {
        $mapper = $this->createMock(NewflagMapper::class);
        $mapper->method('getMinId')->willReturn(500);
        $mapper->expects($this->never())->method('addFlags');

        (new NewflagService($mapper))->markRead(1, 1, [100, 200, 500]);
    }

    public function testMarkReadTrimsOldestFlagsWhenLimitExceeded(): void
    {
        $mapper = $this->createMock(NewflagMapper::class);
        $mapper->method('getMinId')->willReturn(0);
        // Current count is 998, adding 5 exceeds limit of 1000
        $mapper->method('countFlags')->willReturn(998);
        $mapper->method('getMinFlagId')->willReturn(50);

        $mapper->expects($this->once())->method('deleteOldest')
            ->with(1, 1, 3); // 998 + 5 - 1000 = 3
        $mapper->expects($this->once())->method('setMinId')
            ->with(1, 1, 50);
        $mapper->expects($this->once())->method('addFlags');

        (new NewflagService($mapper))->markRead(1, 1, [10, 20, 30, 40, 50]);
    }

    // -------------------------------------------------------------------------
    // markForumRead()
    // -------------------------------------------------------------------------

    public function testMarkForumReadDoesNothingForGuest(): void
    {
        $mapper = $this->createMock(NewflagMapper::class);
        $mapper->expects($this->never())->method('getMaxMessageId');
        (new NewflagService($mapper))->markForumRead(0, 1);
    }

    public function testMarkForumReadClearsFlagsAndSetsMinId(): void
    {
        $mapper = $this->createMock(NewflagMapper::class);
        $mapper->method('getMaxMessageId')->willReturn(999);
        $mapper->expects($this->once())->method('deleteAllFlags')->with(5, 1);
        $mapper->expects($this->once())->method('setMinId')->with(5, 1, 999);

        (new NewflagService($mapper))->markForumRead(5, 1);
    }

    public function testMarkForumReadSkipsSetMinIdWhenNoMessages(): void
    {
        $mapper = $this->createMock(NewflagMapper::class);
        $mapper->method('getMaxMessageId')->willReturn(0);
        $mapper->expects($this->once())->method('deleteAllFlags');
        $mapper->expects($this->never())->method('setMinId');

        (new NewflagService($mapper))->markForumRead(5, 1);
    }

    // -------------------------------------------------------------------------
    // getNewMessageIds()
    // -------------------------------------------------------------------------

    public function testGetNewMessageIdsReturnsEmptyForGuest(): void
    {
        $mapper = $this->createMock(NewflagMapper::class);
        $result = (new NewflagService($mapper))->getNewMessageIds(0, 1, [1, 2, 3]);
        $this->assertSame([], $result);
    }

    public function testGetNewMessageIdsReturnsEmptyForEmptyInput(): void
    {
        $mapper = $this->createMock(NewflagMapper::class);
        $result = (new NewflagService($mapper))->getNewMessageIds(1, 1, []);
        $this->assertSame([], $result);
    }

    public function testGetNewMessageIdsFiltersReadAndBelowMin(): void
    {
        $mapper = $this->createMock(NewflagMapper::class);
        $mapper->method('getMinId')->willReturn(10);
        $mapper->method('getFlags')->willReturn([20 => true]); // message 20 already flagged read

        $result = (new NewflagService($mapper))->getNewMessageIds(1, 1, [5, 10, 15, 20, 25]);
        // 5 and 10 are at or below min; 20 is flagged; 15 and 25 are new
        $this->assertSame([15, 25], $result);
    }

    // -------------------------------------------------------------------------
    // getNewCountsForForums()
    // -------------------------------------------------------------------------

    public function testGetNewCountsForForumsReturnsEmptyForGuest(): void
    {
        $mapper = $this->createMock(NewflagMapper::class);
        $result = (new NewflagService($mapper))->getNewCountsForForums(0, [1, 2]);
        $this->assertSame([], $result);
    }

    public function testGetNewCountsForForumsReturnsEmptyForEmptyList(): void
    {
        $mapper = $this->createMock(NewflagMapper::class);
        $result = (new NewflagService($mapper))->getNewCountsForForums(1, []);
        $this->assertSame([], $result);
    }

    public function testGetNewCountsForForumsDelegatesToMapper(): void
    {
        $mapper = $this->createMock(NewflagMapper::class);
        $mapper->method('countNewPerForum')->willReturn([1 => 3, 2 => 7]);
        $result = (new NewflagService($mapper))->getNewCountsForForums(1, [1, 2]);
        $this->assertSame([1 => 3, 2 => 7], $result);
    }
}
