<?php

declare(strict_types=1);

namespace WyriHaximus\React;

use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use WyriHaximus\React\Mutex\Memory;
use WyriHaximus\React\Mutex\MutexInterface;

use const WyriHaximus\Constants\Boolean\FALSE_;

final class Cron
{
    /** @var array<ActionInterface> */
    private array $actions;

    private MutexInterface $mutex;

    private function __construct(SchedulerInterface $scheduler, MutexInterface $mutex, ActionInterface ...$actions)
    {
        $this->actions = $actions;
        $this->mutex   = $mutex;

        $scheduler->schedule(function (): void {
            $this->tick();
        });
    }

    public static function create(LoopInterface $loop, ActionInterface ...$actions): self
    {
        return new self(new Scheduler($loop), new Memory(), ...$actions);
    }

    public static function createHighPrecision(LoopInterface $loop, ActionInterface ...$actions): self
    {
        return new self(new HighPrecisionScheduler($loop), new Memory(), ...$actions);
    }

    public static function createWithMutex(LoopInterface $loop, MutexInterface $mutex, ActionInterface ...$actions): self
    {
        return new self(new Scheduler($loop), $mutex, ...$actions);
    }

    public static function createHighPrecisionWithMutex(LoopInterface $loop, MutexInterface $mutex, ActionInterface ...$actions): self
    {
        return new self(new HighPrecisionScheduler($loop), $mutex, ...$actions);
    }

    private function tick(): void
    {
        foreach ($this->actions as $action) {
            if (! $action->isDue()) {
                continue;
            }

            $this->perform($action);
        }
    }

    private function perform(ActionInterface $action): void
    {
        /**
         * @phpstan-ignore-next-line
         * @psalm-suppress MissingClosureParamType
         * @psalm-suppress TooManyTemplateParams
         * @psalm-suppress UndefinedInterfaceMethod
         */
        $this->mutex->acquire($action->key())->then(function ($lock) use ($action): void {
            if ($lock === FALSE_) {
                return;
            }

            $action->perform()->then(fn (): PromiseInterface => $this->mutex->release($lock));
        })->done();
    }
}
