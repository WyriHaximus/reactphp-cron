<?php declare(strict_types=1);

namespace WyriHaximus\Tests\React;

use ApiClients\Tools\TestUtilities\TestCase;
use React\EventLoop\Factory;
use WyriHaximus\React\Action;
use WyriHaximus\React\Cron;

/**
 * @internal
 */
final class CronFunctionalTest extends TestCase
{
    public function provideFactoryMethods(): iterable
    {
        yield 'default' => ['create'];
        yield 'high_precision' => ['createHighPrecision'];
    }

    /**
     * @param string $factoryMethod
     *
     * @dataProvider provideFactoryMethods
     */
    public function testScheduling(string $factoryMethod): void
    {
        $loop = Factory::create();

        $ran = false;
        $ranTimes = 0;
        $action = new Action('name', '* * * * *', function () use (&$ran, &$ranTimes, $loop): void {
            $ran = true;
            $ranTimes++;

            if ($ranTimes >= 2) {
                $loop->stop();
            }
        });

        Cron::$factoryMethod($loop, $action);
        $loop->run();

        self::assertTrue($ran);
        self::assertSame(2, $ranTimes);
    }

    /**
     * @param string $factoryMethod
     *
     * @dataProvider provideFactoryMethods
     */
    public function testMutexLockOnlyAllowsTheSameActionOnce(string $factoryMethod): void
    {
        $loop = Factory::create();

        $ran = false;
        $ranTimes = 0;
        $action = new Action('name', '* * * * *', function () use (&$ran, &$ranTimes, $loop): void {
            $loop->futureTick(function () use (&$ran, &$ranTimes, $loop): void {
                $ran = true;
                $ranTimes++;

                if ($ranTimes >= 2) {
                    $loop->stop();
                }
            });
        });

        Cron::$factoryMethod($loop, $action, $action);
        $loop->run();

        self::assertTrue($ran);
        self::assertSame(2, $ranTimes);
    }
}
