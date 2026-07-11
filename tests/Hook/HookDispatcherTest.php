<?php
declare(strict_types=1);

namespace Phorum\Tests\Hook;

use Phorum\Hook\HookDispatcher;
use PHPUnit\Framework\TestCase;

class HookDispatcherTest extends TestCase
{
    protected function setUp(): void
    {
        HookDispatcher::reset();
    }

    protected function tearDown(): void
    {
        HookDispatcher::reset();
    }

    // -------------------------------------------------------------------------
    // Singleton
    // -------------------------------------------------------------------------

    public function testGetInstanceReturnsSameObject(): void
    {
        $a = HookDispatcher::getInstance();
        $b = HookDispatcher::getInstance();
        $this->assertSame($a, $b);
    }

    public function testResetYieldsNewInstance(): void
    {
        $before = HookDispatcher::getInstance();
        HookDispatcher::reset();
        $after = HookDispatcher::getInstance();
        $this->assertNotSame($before, $after);
    }

    // -------------------------------------------------------------------------
    // dispatch() with no handlers
    // -------------------------------------------------------------------------

    public function testDispatchWithNoHandlersReturnsDataUnchanged(): void
    {
        $result = HookDispatcher::getInstance()->dispatch('no_handler', 'original');
        $this->assertSame('original', $result);
    }

    public function testDispatchWithNoHandlersReturnsNullByDefault(): void
    {
        $result = HookDispatcher::getInstance()->dispatch('no_handler');
        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // dispatch() with a single handler
    // -------------------------------------------------------------------------

    public function testSingleHandlerTransformsData(): void
    {
        HookDispatcher::getInstance()->register('test', fn($d) => strtoupper($d));
        $result = HookDispatcher::getInstance()->dispatch('test', 'hello');
        $this->assertSame('HELLO', $result);
    }

    public function testHandlerReturningNullLeavesDataUnchanged(): void
    {
        HookDispatcher::getInstance()->register('test', fn($d) => null);
        $result = HookDispatcher::getInstance()->dispatch('test', 'unchanged');
        $this->assertSame('unchanged', $result);
    }

    // -------------------------------------------------------------------------
    // dispatch() with multiple handlers
    // -------------------------------------------------------------------------

    public function testMultipleHandlersChainingOutput(): void
    {
        $d = HookDispatcher::getInstance();
        $d->register('test', fn($v) => $v . 'A');
        $d->register('test', fn($v) => $v . 'B');
        $d->register('test', fn($v) => $v . 'C');
        $this->assertSame('xABC', $d->dispatch('test', 'x'));
    }

    public function testEachHandlerReceivesPreviousHandlersOutput(): void
    {
        $received = [];
        $d        = HookDispatcher::getInstance();
        $d->register('test', function ($v) use (&$received) { $received[] = $v; return $v + 1; });
        $d->register('test', function ($v) use (&$received) { $received[] = $v; return $v + 1; });
        $d->dispatch('test', 10);
        $this->assertSame([10, 11], $received);
    }

    // -------------------------------------------------------------------------
    // Priority ordering
    // -------------------------------------------------------------------------

    public function testLowerPriorityRunsFirst(): void
    {
        $order = [];
        $d     = HookDispatcher::getInstance();
        $d->register('test', function ($v) use (&$order) { $order[] = 'high'; return $v; }, priority: 20);
        $d->register('test', function ($v) use (&$order) { $order[] = 'low';  return $v; }, priority: 5);
        $d->register('test', function ($v) use (&$order) { $order[] = 'mid';  return $v; }, priority: 10);
        $d->dispatch('test', null);
        $this->assertSame(['low', 'mid', 'high'], $order);
    }

    public function testHandlersWithSamePriorityRunInRegistrationOrder(): void
    {
        $order = [];
        $d     = HookDispatcher::getInstance();
        $d->register('test', function ($v) use (&$order) { $order[] = 1; return $v; });
        $d->register('test', function ($v) use (&$order) { $order[] = 2; return $v; });
        $d->register('test', function ($v) use (&$order) { $order[] = 3; return $v; });
        $d->dispatch('test', null);
        $this->assertSame([1, 2, 3], $order);
    }

    // -------------------------------------------------------------------------
    // Extra $args
    // -------------------------------------------------------------------------

    public function testExtraArgsArePassedToEveryHandler(): void
    {
        $received = [];
        HookDispatcher::getInstance()->register(
            'test',
            function ($data, $extra) use (&$received) {
                $received[] = $extra;
                return $data;
            }
        );
        HookDispatcher::getInstance()->dispatch('test', 'data', 'bonus');
        $this->assertSame(['bonus'], $received);
    }

    // -------------------------------------------------------------------------
    // hasHook()
    // -------------------------------------------------------------------------

    public function testHasHookReturnsFalseWhenNothingRegistered(): void
    {
        $this->assertFalse(HookDispatcher::getInstance()->hasHook('missing'));
    }

    public function testHasHookReturnsTrueAfterRegistration(): void
    {
        HookDispatcher::getInstance()->register('present', fn($d) => $d);
        $this->assertTrue(HookDispatcher::getInstance()->hasHook('present'));
    }

    // -------------------------------------------------------------------------
    // Procedural wrapper
    // -------------------------------------------------------------------------

    public function testProceduralWrapperDelegatesToDispatcher(): void
    {
        require_once dirname(__DIR__, 2) . '/src/Hook/functions.php';

        HookDispatcher::getInstance()->register('greet', fn($d) => 'Hello, ' . $d . '!');
        $result = phorum_api_hook('greet', 'world');
        $this->assertSame('Hello, world!', $result);
    }
}
