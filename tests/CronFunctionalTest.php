<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\React\Cron;

use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;
use WyriHaximus\React\Cron;
use WyriHaximus\React\Cron\Action;
use WyriHaximus\React\Mutex\Memory;

use function array_unshift;

final class CronFunctionalTest extends AsyncTestCase
{
    /**
     * @return iterable<string, array<mixed>>
     */
    public function provideFactoryMethods(): iterable
    {
        yield 'default' => [
            'create',
            Factory::create(),
            [],
        ];

        yield 'default_mutex' => [
            'createWithMutex',
            Factory::create(),
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
    public function scheduling(string $factoryMethod, LoopInterface $loop, array $args): void
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

        array_unshift($args, $loop);
        $args[] = $action;
        /** @phpstan-ignore-next-line */
        $cron = Cron::$factoryMethod(...$args);
        $this->await($deferred->promise(), $loop, 150);

        self::assertTrue($ran);
        self::assertSame(2, $ranTimes);
    }

    /**
     * @param array<mixed> $args
     *
     * @test
     * @dataProvider provideFactoryMethods
     */
    public function mutexLockOnlyAllowsTheSameActionOnce(string $factoryMethod, LoopInterface $loop, array $args): void
    {
        $ran      = false;
        $ranTimes = 0;
        $cron     = null;
        $deferred = new Deferred();
        $action   = new Action('name', 0.1, '* * * * *', static function () use (&$ran, &$ranTimes, &$cron, $loop, $deferred): void {
            $loop->futureTick(static function () use (&$ran, &$ranTimes, &$cron, $deferred): void {
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

        array_unshift($args, $loop);
        $args[] = $action;
        $args[] = $action;
        /** @phpstan-ignore-next-line */
        $cron = Cron::$factoryMethod(...$args);
        $this->await($deferred->promise(), $loop, 150);

        self::assertTrue($ran);
        self::assertSame(2, $ranTimes);
    }
}
