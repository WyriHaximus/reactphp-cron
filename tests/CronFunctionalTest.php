<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\React\Cron;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
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

    /** @param array<mixed> $args */
    #[Test]
    #[DataProvider('provideFactoryMethods')]
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

            /** @phpstan-ignore-next-line */
            $cron->stop();
            Loop::futureTick(static fn () => $deferred->resolve(null));
        });

        $args[] = $action;
        /** @phpstan-ignore-next-line */
        $cron = Cron::$factoryMethod(...$args);
        await($deferred->promise());

        self::assertTrue($ran);
        self::assertSame(2, $ranTimes);
    }

    /** @param array<mixed> $args */
    #[Test]
    #[DataProvider('provideFactoryMethods')]
    public function mutexLockOnlyAllowsTheSameActionOnce(string $factoryMethod, array $args): void
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
        await($deferred->promise());

        self::assertTrue($ran);
        self::assertSame(2, $ranTimes);
    }

    /** @param array<mixed> $args */
    #[Test]
    #[DataProvider('provideFactoryMethods')]
    public function exceptionForwarding(string $factoryMethod, array $args): void
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
    public function runOnStartUp(string $factoryMethod, array $args): void
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
        await($deferred->promise());

        self::assertTrue($ran);
        self::assertSame(1, $ranTimes);
    }
}
