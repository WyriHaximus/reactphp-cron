<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\React;

use React\EventLoop\LoopInterface;
use React\EventLoop\StreamSelectLoop;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;
use WyriHaximus\React\Action;
use WyriHaximus\React\Cron;
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
        $action   = new Action('name', '* * * * *', static function () use (&$ran, &$ranTimes, $loop): void {
            $ran = true;
            $ranTimes++;

            if ($ranTimes < 2) {
                return;
            }

            $loop->stop();
        });

        array_unshift($args, $loop);
        $args[] = $action;
        /** @phpstan-ignore-next-line */
        Cron::$factoryMethod(...$args);
        $loop->run();

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
        $action   = new Action('name', '* * * * *', static function () use (&$ran, &$ranTimes, $loop): void {
            $loop->futureTick(static function () use (&$ran, &$ranTimes, $loop): void {
                $ran = true;
                $ranTimes++;

                if ($ranTimes < 2) {
                    return;
                }

                $loop->stop();
            });
        });

        array_unshift($args, $loop);
        $args[] = $action;
        $args[] = $action;
        /** @phpstan-ignore-next-line */
        Cron::$factoryMethod(...$args);
        $loop->run();

        self::assertTrue($ran);
        self::assertSame(2, $ranTimes);
    }
}
