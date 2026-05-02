<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\React\Cron;

use DateTimeImmutable;
use DateTimeZone;
use Lcobucci\Clock\FrozenClock;
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
use WyriHaximus\React\Cron\ActionInterface;
use WyriHaximus\React\Cron\Scheduler;
use WyriHaximus\React\Mutex\Contracts\MutexInterface;
use WyriHaximus\React\Mutex\Memory;

use function array_filter;
use function array_first;
use function array_shift;
use function count;
use function is_callable;
use function React\Async\await;

#[TimeOut(69)]
final class CronFunctionalTest extends AsyncTestCase
{
    /** @return iterable<string, array<mixed>> */
    public static function provideFactoryMethods(): iterable
    {
        $clockTime          = new DateTimeImmutable('1975-05-07T19:00:00Z', new DateTimeZone('UTC'));
        $oneSecondClockTick = static fn (FrozenClock $clock) => $clock->adjustTime('+1 second');
        $timingTestSets     = [];
        $testClockTickets   = [];
        for ($i = 0; $i < 6; ++$i) {
            $testClockTickets                        = [$oneSecondClockTick, ...$testClockTickets];
            $clockTime                               = $clockTime->modify('-1 second');
            $timingTestSets[$clockTime->format('c')] = $testClockTickets;
        }

        foreach ($timingTestSets as $setTo => $clockTicks) {
            yield 'default_' . $setTo => [
                'create',
                [],
                self::getClock($setTo),
                true,
                ...$clockTicks,
            ];

            yield 'default_with_system_clock_' . $setTo => (static function (string $setTo, callable ...$clockTicks): array {
                $clock = self::getClock($setTo);

                return [
                    'createWithClock',
                    [$clock],
                    $clock,
                    false,
                    ...$clockTicks,
                ];
            })($setTo, ...$clockTicks);

            yield 'default_with_memory_mutex_' . $setTo => [
                'createWithMutex',
                [
                    new Memory(),
                ],
                self::getClock($setTo),
                true,
                ...$clockTicks,
            ];

            yield 'default_with_system_clock_and_memory_mutex_' . $setTo => (static function (string $setTo, callable ...$clockTicks): array {
                $clock = self::getClock($setTo);

                return [
                    'createWithClockAndMutex',
                    [
                        new Memory(),
                        $clock,
                    ],
                    $clock,
                    false,
                    ...$clockTicks,
                ];
            })($setTo, ...$clockTicks);
        }
    }

    /** @param array<ClockInterface|MutexInterface|ActionInterface> $args */
    #[Test]
    #[DataProvider('provideFactoryMethods')]
    public function scheduling(string $factoryMethod, array $args, FrozenClock $clock, bool $overRideClock, callable ...$clockTicks): void
    {
        self::handleClockTicks($clock, ...$clockTicks);

        $ran      = false;
        $ranTimes = 0;
        /** @var ?Cron $cron */
        $cron     = null;
        $deferred = new Deferred();
        $action   = new Action('name', 0.1, '* * * * *', static function () use (&$ran, &$ranTimes, &$cron, $deferred): void {
            $ran = true;
            $ranTimes++;

            if ($ranTimes < 2) {
                return;
            }

            $cron?->stop();
            Loop::futureTick(static fn () => $deferred->resolve(null));
        });

        $args[] = $action;
        $cron   = $this->cronFactory($factoryMethod, ...$args);
        if ($overRideClock) {
            $this->overRideClock($cron, $clock);
        }

        await($deferred->promise());

        self::assertTrue($ran);
        self::assertSame(2, $ranTimes);
    }

    /** @param array<ClockInterface|MutexInterface|ActionInterface> $args */
    #[Test]
    #[DataProvider('provideFactoryMethods')]
    public function mutexLockOnlyAllowsTheSameActionOnce(string $factoryMethod, array $args, FrozenClock $clock, bool $overRideClock, callable ...$clockTicks): void
    {
        self::handleClockTicks($clock, ...$clockTicks);

        $ran      = false;
        $ranTimes = 0;
        /** @var ?Cron $cron */
        $cron     = null;
        $deferred = new Deferred();
        $action   = new Action('name', 0.1, '* * * * *', static function () use (&$ran, &$ranTimes, &$cron, $deferred): void {
            $ran = true;
            $ranTimes++;

            if ($ranTimes < 2) {
                return;
            }

            $cron?->stop();
            Loop::futureTick(static fn () => $deferred->resolve(null));
        });

        $args[] = $action;
        $args[] = $action;
        $cron   = $this->cronFactory($factoryMethod, ...$args);
        if ($overRideClock) {
            $this->overRideClock($cron, $clock);
        }

        await($deferred->promise());

        self::assertTrue($ran);
        self::assertSame(2, $ranTimes);
    }

    /** @param array<ClockInterface|MutexInterface|ActionInterface> $args */
    #[Test]
    #[DataProvider('provideFactoryMethods')]
    public function exceptionForwarding(string $factoryMethod, array $args, FrozenClock $clock, bool $overRideClock, callable ...$clockTicks): void
    {
        self::handleClockTicks($clock, ...$clockTicks);

        $error = null;
        $ran   = false;
        /** @var ?Cron $cron */
        $cron     = null;
        $deferred = new Deferred();
        $action   = new Action('name', 0.1, '* * * * *', static function () use (&$ran, &$cron, $deferred): never {
            Loop::futureTick(static fn () => $deferred->resolve(null));

            $ran = true;

            $cron?->stop();

            throw new RuntimeException('Action goes boom!');
        });

        $args[] = $action;
        $cron   = $this->cronFactory($factoryMethod, ...$args);
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

    /** @param array<ClockInterface|MutexInterface|ActionInterface> $args */
    #[Test]
    #[DataProvider('provideFactoryMethods')]
    public function runOnStartUp(string $factoryMethod, array $args, FrozenClock $clock, bool $overRideClock, callable ...$clockTicks): void
    {
        self::handleClockTicks($clock, ...$clockTicks);

        $ran      = false;
        $ranTimes = 0;
        /** @var ?Cron $cron */
        $cron     = null;
        $deferred = new Deferred();
        $action   = new Cron\RunOnStartUpAction('name', 0.1, '* * * * *', static function () use (&$ran, &$ranTimes, &$cron, $deferred): void {
            $ran = true;
            $ranTimes++;
            $cron?->stop();
            Loop::futureTick(static fn () => $deferred->resolve(null));
        });

        $args[] = $action;
        $cron   = $this->cronFactory($factoryMethod, ...$args);
        await($deferred->promise());

        self::assertTrue($ran);
        self::assertSame(1, $ranTimes);
    }

    private function cronFactory(string $factoryMethod, ClockInterface|MutexInterface|ActionInterface ...$args): Cron
    {
        /** @var non-empty-array<ActionInterface> $actions */
        $actions = array_filter($args, static fn (ClockInterface|MutexInterface|ActionInterface $arg): bool => $arg instanceof ActionInterface);
        /** @var non-empty-array<ClockInterface> $clocks */
        $clocks = array_filter($args, static fn (ClockInterface|MutexInterface|ActionInterface $arg): bool => $arg instanceof ClockInterface);
        /** @var non-empty-array<MutexInterface> $mutexs */
        $mutexs = array_filter($args, static fn (ClockInterface|MutexInterface|ActionInterface $arg): bool => $arg instanceof MutexInterface);

        return match ($factoryMethod) {
            'create' => Cron::create(...$actions),
            'createWithClock' => Cron::createWithClock(array_first($clocks), ...$actions),
            'createWithMutex' => Cron::createWithMutex(array_first($mutexs), ...$actions),
            'createWithClockAndMutex' => Cron::createWithClockAndMutex(array_first($mutexs), array_first($clocks), ...$actions),
            default => throw new RuntimeException('Factory method not implemented: ' . $factoryMethod),
        };
    }

    private function overRideClock(Cron $cron, FrozenClock $clock): void
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

    private static function getClock(string $setTo): ClockInterface
    {
        $clock = FrozenClock::fromUTC();
        $clock->setTo(new DateTimeImmutable($setTo));

        return $clock;
    }

    private static function handleClockTicks(FrozenClock $clock, callable ...$clockTicks): void
    {
        Loop::futureTick(static function () use ($clock, $clockTicks): void {
            $clockTick = array_shift($clockTicks);

            if (is_callable($clockTick)) {
                $clockTick($clock);
            }

            if (count($clockTicks) <= 0) {
                return;
            }

            self::handleClockTicks($clock, ...$clockTicks);
        });
    }
}
