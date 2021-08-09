<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\React\Cron;

use React\EventLoop\Loop;
use React\Promise\Deferred;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;
use WyriHaximus\React\Cron;
use WyriHaximus\React\Cron\Action;
use WyriHaximus\React\Mutex\Memory;

final class CronFunctionalTest extends AsyncTestCase
{
    /**
     * @return iterable<string, array<mixed>>
     */
    public function provideFactoryMethods(): iterable
    {
        yield 'default' => [
            'create',
            [],
        ];

        yield 'default_with_memory_mutex' => [
            'createWithMutex',
            [
                new Memory(),
            ],
        ];
    }

    /**
     * @param array<mixed> $args
     *
     * @test
     * @dataProvider provideFactoryMethods
     */
    public function scheduling(string $factoryMethod, array $args): void
    {
        $ran      = false;
        $ranTimes = 0;
        $cron     = null;
        $deferred = new Deferred();
        $action   = new Action('name', 0.1, '* * * * *', static function () use (&$ran, &$ranTimes, &$cron, $deferred): void {
            $ran = true;
            $ranTimes++;

            if ($ranTimes < 2) {
                return;
            }

            /**
             * @phpstan-ignore-next-line
             */
            $cron->stop();
            $deferred->resolve();
        });

        $args[] = $action;
        /** @phpstan-ignore-next-line */
        $cron = Cron::$factoryMethod(...$args);
        $this->await($deferred->promise(), 150);

        self::assertTrue($ran);
        self::assertSame(2, $ranTimes);
    }

    /**
     * @param array<mixed> $args
     *
     * @test
     * @dataProvider provideFactoryMethods
     */
    public function mutexLockOnlyAllowsTheSameActionOnce(string $factoryMethod, array $args): void
    {
        $ran      = false;
        $ranTimes = 0;
        $cron     = null;
        $deferred = new Deferred();
        $action   = new Action('name', 0.1, '* * * * *', static function () use (&$ran, &$ranTimes, &$cron, $deferred): void {
            Loop::futureTick(static function () use (&$ran, &$ranTimes, &$cron, $deferred): void {
                $ran = true;
                $ranTimes++;

                if ($ranTimes < 2) {
                    return;
                }

                /**
                 * @phpstan-ignore-next-line
                 */
                $cron->stop();
                $deferred->resolve();
            });
        });

        $args[] = $action;
        $args[] = $action;
        /** @phpstan-ignore-next-line */
        $cron = Cron::$factoryMethod(...$args);
        $this->await($deferred->promise(), 150);

        self::assertTrue($ran);
        self::assertSame(2, $ranTimes);
    }
}
