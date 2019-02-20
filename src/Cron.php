<?php declare(strict_types=1);

namespace WyriHaximus\React;

use React\EventLoop\LoopInterface;

final class Cron
{
    /** @var LoopInterface */
    private $loop;

    /** @var Action[] */
    private $actions;

    /**
     * Cron constructor.
     * @param LoopInterface $loop
     * @param Action[]      $actions
     */
    public function __construct(LoopInterface $loop, Action ...$actions)
    {
        $this->loop = $loop;
        $this->actions = $actions;

        $this->schedule();
    }

    private function schedule(): void
    {
        // Tick every 60 seconds
        $this->loop->addPeriodicTimer(60, function (): void {
            $this->tick();
        });

        // Initial tick because some actions might want to run in this minute
        $this->tick();
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
