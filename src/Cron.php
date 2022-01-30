<?php

declare(strict_types=1);

namespace WyriHaximus\React;

use WyriHaximus\React\Cron\ActionInterface;
use WyriHaximus\React\Cron\Scheduler;
use WyriHaximus\React\Mutex\Contracts\LockInterface;
use WyriHaximus\React\Mutex\Contracts\MutexInterface;
use WyriHaximus\React\Mutex\Memory;

use function React\Async\async;
use function React\Async\await;

final class Cron
{
    /** @var array<ActionInterface> */
    private array $actions;

    private Scheduler $scheduler;

    private MutexInterface $mutex;

    private function __construct(MutexInterface $mutex, ActionInterface ...$actions)
    {
        $this->scheduler = new Scheduler();
        $this->actions   = $actions;
        $this->mutex     = $mutex;

        $this->scheduler->schedule(function (): void {
            $this->tick();
        });
    }

    public static function create(ActionInterface ...$actions): self
    {
        return self::createWithMutex(new Memory(), ...$actions);
    }

    public static function createWithMutex(MutexInterface $mutex, ActionInterface ...$actions): self
    {
        return new self($mutex, ...$actions);
    }

    public function stop(): void
    {
        $this->scheduler->stop();
    }

    private function tick(): void
    {
        foreach ($this->actions as $action) {
            if (! $action->isDue()) {
                continue;
            }

            /**
             * @phpstan-ignore-next-line
             * @psalm-suppress UndefinedInterfaceMethod
             */
            async(fn () => $this->perform($action))()->done();
        }
    }

    private function perform(ActionInterface $action): void
    {
        /**
         * @psalm-suppress MissingClosureParamType
         * @psalm-suppress TooManyTemplateParams
         * @psalm-suppress UndefinedInterfaceMethod
         * @var ?LockInterface $lock
         */
        $lock = await($this->mutex->acquire($action->key(), $action->mutexTtl()));
        if ($lock === null) {
            return;
        }

        $action->perform();

        /**
         * @psalm-suppress MissingClosureParamType
         * @psalm-suppress TooManyTemplateParams
         * @psalm-suppress UndefinedInterfaceMethod
         */
        $this->mutex->release($lock);
    }
}
