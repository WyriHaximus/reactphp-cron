<?php declare(strict_types=1);

namespace WyriHaximus\React;

use React\EventLoop\LoopInterface;

final class Cron
{
    /** @var ActionInterface[] */
    private $actions;

    /**
     * @param SchedulerInterface $scheduler
     * @param ActionInterface[]  $actions
     */
    private function __construct(SchedulerInterface $scheduler, ActionInterface ...$actions)
    {
        $this->actions = $actions;

        $scheduler->schedule(function (): void {
            $this->tick();
        });
    }

    public static function create(LoopInterface $loop, ActionInterface ...$actions)
    {
        return new self(new Scheduler($loop), ...$actions);
    }

    private function tick(): void
    {
        foreach ($this->actions as $action) {
            if ($action->isDue() === true) {
                $action->perform();
            }
        }
    }
}
