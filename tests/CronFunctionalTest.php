<?php declare(strict_types=1);

namespace WyriHaximus\Tests\React;

use ApiClients\Tools\TestUtilities\TestCase;
use React\EventLoop\LoopInterface;
use React\EventLoop\StreamSelectLoop;
use WyriHaximus\React\Action;
use WyriHaximus\React\Cron;
use WyriHaximus\React\Mutex\Memory;

/**
 * @internal
 */
final class CronFunctionalTest extends TestCase
{
    public function provideFactoryMethods(): iterable
    {
        yield 'default' => [
            'create',
            new StreamSelectLoop(),
            [],
        ];

        yield 'high_precision' => [
            'createHighPrecision',
            new StreamSelectLoop(),
            [],
        ];

        yield 'default_mutex' => [
            'createWithMutex',
            new StreamSelectLoop(),
            [
                new Memory(),
            ],
        ];

        yield 'high_precision_mutex' => [
            'createHighPrecisionWithMutex',
            new StreamSelectLoop(),
            [
                new Memory(),
            ],
        ];
    }

    /**
     * @param string        $factoryMethod
     * @param LoopInterface $loop
     * @param array         $args
     *
     * @dataProvider provideFactoryMethods
     */
    public function testScheduling(string $factoryMethod, LoopInterface $loop, array $args): void
    {
        $ran = false;
        $ranTimes = 0;
        $action = new Action('name', '* * * * *', function () use (&$ran, &$ranTimes, $loop): void {
            $ran = true;
            $ranTimes++;

            if ($ranTimes >= 2) {
                $loop->stop();
            }
        });

        \array_unshift($args, $loop);
        $args[] = $action;
        Cron::$factoryMethod(...$args);
        $loop->run();

        self::assertTrue($ran);
        self::assertSame(2, $ranTimes);
    }

    /**
     * @param string        $factoryMethod
     * @param LoopInterface $loop
     * @param array         $args
     *
     * @dataProvider provideFactoryMethods
     */
    public function testMutexLockOnlyAllowsTheSameActionOnce(string $factoryMethod, LoopInterface $loop, array $args): void
    {
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

        \array_unshift($args, $loop);
        $args[] = $action;
        $args[] = $action;
        Cron::$factoryMethod(...$args);
        $loop->run();

        self::assertTrue($ran);
        self::assertSame(2, $ranTimes);
    }
}
