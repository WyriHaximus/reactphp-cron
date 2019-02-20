<?php declare(strict_types=1);

namespace WyriHaximus\React;

use React\EventLoop\LoopInterface;

final class Scheduler implements SchedulerInterface
{
    /** @var LoopInterface */
    private $loop;

    /**
     * Scheduler constructor.
     * @param LoopInterface $loop
     */
    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
    }

    public function schedule(callable $tick): void
    {
        // Tick every 60 seconds
        $this->loop->addPeriodicTimer(60, function () use ($tick): void {
            $tick();
        });

        // Initial tick because some actions might want to run in this minute
        $tick();
    }
}
