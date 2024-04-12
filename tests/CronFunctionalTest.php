<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\React\Cron;

use Lcobucci\Clock\FrozenClock;
use Lcobucci\Clock\SystemClock;
use Psr\Clock\ClockInterface;
use React\EventLoop\Loop;
use React\Promise\Deferred;
use RuntimeException;
use Throwable;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;
use WyriHaximus\AsyncTestUtilities\TimeOut;
use WyriHaximus\React\Cron;
use WyriHaximus\React\Cron\Action;
use WyriHaximus\React\Mutex\Memory;

use function React\Async\await;

#[TimeOut(300)]
final class CronFunctionalTest extends AsyncTestCase
{
    /** @return iterable<string, array<mixed>> */
    public static function provideFactoryMethods(): iterable
    {
        $clock = FrozenClock::fromUTC();

        while ((int) $clock->now()->format('s') > 0) {
            $clock->setTo($clock->now()->modify('+1 second'));
        }

        yield 'default' => [
            'create',
            [],
            $clock,
            true,
        ];

        yield 'default_with_frozen_clock' => [
            'createWithClock',
            [
                $clock,
            ],
            $clock,
            false,
        ];

        yield 'default_with_memory_mutex' => [
            'createWithMutex',
            [
                new Memory(),
            ],
            $clock,
            true,
        ];

        yield 'default_with_frozen_clock_and_memory_mutex' => [
            'createWithClockAndMutex',
            [
                new Memory(),
                $clock,
            ],
            $clock,
            false,
        ];
    }

    /**
     * @param array<mixed> $args
     *
     * @test
     * @dataProvider provideFactoryMethods
     */
    public function scheduling(string $factoryMethod, array $args, FrozenClock $clock, bool $overRideClock): void
    {
        $ran      = false;
        $ranTimes = 0;
        $cron     = null;
        $deferred = new Deferred();
        $action   = new Action('name', 0.1, '* * * * *', static function () use (&$ran, &$ranTimes, &$cron, $deferred, $clock): void {
            $ran = true;
            $ranTimes++;

            $this->setClockToAlmostNextMinute($clock);

            if ($ranTimes < 2) {
                return;
            }

            /** @phpstan-ignore-next-line */
            $cron->stop();
            Loop::futureTick(static fn () => $deferred->resolve(null));
        });

        $args[] = $action;
        /** @phpstan-ignore-next-line */
        $cron = Cron::$factoryMethod(...$args);
        if ($overRideClock) {
            $this->overRideClock($cron, $clock);
        }

        $this->setClockToAlmostNextMinute($clock);

        await($deferred->promise());

        self::assertTrue($ran);
        self::assertSame(2, $ranTimes);
    }

    /**
     * @param array<mixed> $args
     *
     * @test
     * @dataProvider provideFactoryMethods
     */
    public function mutexLockOnlyAllowsTheSameActionOnce(string $factoryMethod, array $args, FrozenClock $clock, bool $overRideClock): void
    {
        $ran      = false;
        $ranTimes = 0;
        $cron     = null;
        $deferred = new Deferred();
        $action   = new Action('name', 0.1, '* * * * *', static function () use (&$ran, &$ranTimes, &$cron, $deferred, $clock): void {
            $ran = true;
            $ranTimes++;

            $this->setClockToAlmostNextMinute($clock);

            if ($ranTimes < 2) {
                return;
            }

            /** @phpstan-ignore-next-line */
            $cron->stop();
            Loop::futureTick(static fn () => $deferred->resolve(null));
        });

        $args[] = $action;
        $args[] = $action;
        /** @phpstan-ignore-next-line */
        $cron = Cron::$factoryMethod(...$args);
        if ($overRideClock) {
            $this->overRideClock($cron, $clock);
        }

        $this->setClockToAlmostNextMinute($clock);

        await($deferred->promise());

        self::assertTrue($ran);
        self::assertSame(2, $ranTimes);
    }

    /**
     * @param array<mixed> $args
     *
     * @test
     * @dataProvider provideFactoryMethods
     */
    public function exceptionForwarding(string $factoryMethod, array $args, FrozenClock $clock, bool $overRideClock): void
    {
        $error    = null;
        $ran      = false;
        $deferred = new Deferred();
        $action   = new Action('name', 0.1, '* * * * *', static function () use (&$ran, &$cron, $deferred, $clock       ): void {
            Loop::futureTick(static fn () => $deferred->resolve(null));

            $this->setClockToAlmostNextMinute($clock);

            $ran = true;

            /** @phpstan-ignore-next-line */
            $cron->stop();

            throw new RuntimeException('Action goes boom!');
        });

        $args[] = $action;
        /** @phpstan-ignore-next-line */
        $cron = Cron::$factoryMethod(...$args);
        if ($overRideClock) {
            $this->overRideClock($cron, $clock);
        }
        $cron->on('error', static function (Throwable $throwable) use (&$error): void {
            $error = $throwable;
        });

        $this->setClockToAlmostNextMinute($clock);

        await($deferred->promise());

        self::assertTrue($ran);
        self::assertInstanceOf(RuntimeException::class, $error);
        self::assertSame('Action goes boom!', $error->getMessage());
    }

    private function overRideClock(Cron $cron, FrozenClock $clock): void
    {
        $ref = new \ReflectionProperty($cron, 'scheduler');
        $ref->setAccessible(true);

        $scheduler = $ref->getValue($cron);

        $ref = new \ReflectionProperty($scheduler, 'clock');
        $ref->setAccessible(true);

        $ref->setValue($scheduler, $clock);
    }

    private function setClockToAlmostNextMinute(FrozenClock $clock): void
    {
        $clock->setTo($clock->now()->modify('+1 minute'));
    }
}
