<?php

declare(strict_types=1);

namespace WyriHaximus\React;

use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use React\EventLoop\Loop;
use Throwable;
use WyriHaximus\React\Cron\ActionInterface;
use WyriHaximus\React\Cron\Scheduler;
use WyriHaximus\React\Mutex\Contracts\LockInterface;
use WyriHaximus\React\Mutex\Contracts\MutexInterface;
use WyriHaximus\React\Mutex\Memory;

use function React\Async\async;
use function React\Async\await;

/** @event error On caught Throws from action handling */
final class Cron implements EventEmitterInterface
{
    use EventEmitterTrait;

    /** @var array<ActionInterface> */
    private array $actions;

    private Scheduler $scheduler;

    private function __construct(private MutexInterface $mutex, ActionInterface ...$actions)
    {
        $this->scheduler = new Scheduler();
        $this->actions   = $actions;

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

            Loop::futureTick(async(fn () => $this->perform($action)));
        }
    }

    private function perform(ActionInterface $action): void
    {
        try {
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
        } catch (Throwable $throwable) { /** @phpstan-ignore-line */
            $this->emit('error', [$throwable, $action]);
        }
    }
}
