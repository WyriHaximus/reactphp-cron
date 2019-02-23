<?php declare(strict_types=1);

namespace WyriHaximus\React;

use React\EventLoop\LoopInterface;
use WyriHaximus\React\Mutex\Memory;
use WyriHaximus\React\Mutex\MutexInterface;

final class Cron
{
    /** @var ActionInterface[] */
    private $actions;

    /** @var MutexInterface */
    private $mutex;

    /**
     * @param SchedulerInterface $scheduler
     * @param MutexInterface     $mutex
     * @param ActionInterface[]  $actions
     */
    private function __construct(SchedulerInterface $scheduler, MutexInterface $mutex, ActionInterface ...$actions)
    {
        $this->actions = $actions;
        $this->mutex = $mutex;

        $scheduler->schedule(function (): void {
            $this->tick();
        });
    }

    public static function create(LoopInterface $loop, ActionInterface ...$actions)
    {
        return new self(new Scheduler($loop), new Memory(), ...$actions);
    }

    public static function createHighPrecision(LoopInterface $loop, ActionInterface ...$actions)
    {
        return new self(new HighPrecisionScheduler($loop), new Memory(), ...$actions);
    }

    public static function createWithMutex(LoopInterface $loop, MutexInterface $mutex, ActionInterface ...$actions)
    {
        return new self(new Scheduler($loop), $mutex, ...$actions);
    }

    public static function createHighPrecisionWithMutex(LoopInterface $loop, MutexInterface $mutex, ActionInterface ...$actions)
    {
        return new self(new HighPrecisionScheduler($loop), $mutex, ...$actions);
    }

    private function tick(): void
    {
        foreach ($this->actions as $action) {
            $this->perform($action);
        }
    }

    private function perform(ActionInterface $action): void
    {
        if ($action->isDue() === false) {
            return;
        }

        $this->mutex->acquire($action->getKey())->then(function ($lock) use ($action) {
            if ($lock === false) {
                return;
            }

            return $action->perform()->then(function () use ($action, $lock) {
                return $this->mutex->release($lock);
            });
        })->done();
    }
}
