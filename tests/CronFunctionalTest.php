<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\React\Cron;

use Lcobucci\Clock\SystemClock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Clock\ClockInterface;
use React\EventLoop\Loop;
use React\Promise\Deferred;
use ReflectionProperty;
use RuntimeException;
use Throwable;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;
use WyriHaximus\AsyncTestUtilities\TimeOut;
use WyriHaximus\React\Cron;
use WyriHaximus\React\Cron\Action;
use WyriHaximus\React\Cron\Scheduler;
use WyriHaximus\React\Mutex\Memory;

use function React\Async\await;

#[TimeOut(300)]
final class CronFunctionalTest extends AsyncTestCase
{
    /** @return iterable<string, array<mixed>> */
    public static function provideFactoryMethods(): iterable
    {
        $clock = SystemClock::fromUTC();

        yield 'default' => [
            'create',
            [],
            $clock,
            true,
        ];

        yield 'default_with_system_clock' => [
            'createWithClock',
            [$clock],
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

        yield 'default_with_system_clock_and_memory_mutex' => [
            'createWithClockAndMutex',
            [
                new Memory(),
                $clock,
            ],
            $clock,
            false,
        ];
    }

    /** @param array<mixed> $args */
    #[Test]
    #[DataProvider('provideFactoryMethods')]
    public function scheduling(string $factoryMethod, array $args, ClockInterface $clock, bool $overRideClock): void
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

            /** @phpstan-ignore-next-line */
            $cron->stop();
            Loop::futureTick(static fn () => $deferred->resolve(null));
        });

        $args[] = $action;
        /** @phpstan-ignore-next-line */
        $cron = Cron::$factoryMethod(...$args);
        self::assertInstanceOf(Cron::class, $cron);
        if ($overRideClock) {
            $this->overRideClock($cron, $clock);
        }

        await($deferred->promise());

        self::assertTrue($ran);
        self::assertSame(2, $ranTimes);
    }

    /** @param array<mixed> $args */
    #[Test]
    #[DataProvider('provideFactoryMethods')]
    public function mutexLockOnlyAllowsTheSameActionOnce(string $factoryMethod, array $args, ClockInterface $clock, bool $overRideClock): void
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

            /** @phpstan-ignore-next-line */
            $cron->stop();
            Loop::futureTick(static fn () => $deferred->resolve(null));
        });

        $args[] = $action;
        $args[] = $action;
        /** @phpstan-ignore-next-line */
        $cron = Cron::$factoryMethod(...$args);
        self::assertInstanceOf(Cron::class, $cron);
        if ($overRideClock) {
            $this->overRideClock($cron, $clock);
        }

        await($deferred->promise());

        self::assertTrue($ran);
        self::assertSame(2, $ranTimes);
    }

    /** @param array<mixed> $args */
    #[Test]
    #[DataProvider('provideFactoryMethods')]
    public function exceptionForwarding(string $factoryMethod, array $args, ClockInterface $clock, bool $overRideClock): void
    {
        $error    = null;
        $ran      = false;
        $deferred = new Deferred();
        $action   = new Action('name', 0.1, '* * * * *', static function () use (&$ran, &$cron, $deferred): never {
            Loop::futureTick(static fn () => $deferred->resolve(null));

            $ran = true;

            /** @phpstan-ignore-next-line */
            $cron->stop();

            throw new RuntimeException('Action goes boom!');
        });

        $args[] = $action;
        /** @phpstan-ignore-next-line */
        $cron = Cron::$factoryMethod(...$args);
        self::assertInstanceOf(Cron::class, $cron);
        if ($overRideClock) {
            $this->overRideClock($cron, $clock);
        }

        $cron->on('error', static function (Throwable $throwable) use (&$error): void {
            $error = $throwable;
        });

        await($deferred->promise());

        self::assertTrue($ran);
        self::assertInstanceOf(RuntimeException::class, $error);
        self::assertSame('Action goes boom!', $error->getMessage());
    }

    /** @param array<mixed> $args */
    #[Test]
    #[DataProvider('provideFactoryMethods')]
    public function runOnStartUp(string $factoryMethod, array $args, ClockInterface $clock, bool $overRideClock): void
    {
        $ran      = false;
        $ranTimes = 0;
        $cron     = null;
        $deferred = new Deferred();
        $action   = new Cron\RunOnStartUpAction('name', 0.1, '* * * * *', static function () use (&$ran, &$ranTimes, &$cron, $deferred): void {
            $ran = true;
            $ranTimes++;
            /** @phpstan-ignore-next-line */
            $cron->stop();
            Loop::futureTick(static fn () => $deferred->resolve(null));
        });

        $args[] = $action;
        /** @phpstan-ignore-next-line */
        $cron = Cron::$factoryMethod(...$args);
        self::assertInstanceOf(Cron::class, $cron);
        await($deferred->promise());

        self::assertTrue($ran);
        self::assertSame(1, $ranTimes);
    }

    private function overRideClock(Cron $cron, ClockInterface $clock): void
    {
        $ref = new ReflectionProperty($cron, 'scheduler');

        $scheduler = $ref->getValue($cron);
        self::assertInstanceOf(Scheduler::class, $scheduler);
        $scheduler->stop();

        $ticks = new ReflectionProperty($scheduler, 'ticks');
        /** @var array<callable> $tickList */
        $tickList     = $ticks->getValue($scheduler);
        $newScheduler = new Scheduler($clock);
        foreach ($tickList as $tick) {
            $newScheduler->schedule($tick);
        }

        $ref->setValue($cron, $newScheduler);
    }
}
