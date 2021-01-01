<?php

declare(strict_types=1);

namespace WyriHaximus\React;

use React\EventLoop\LoopInterface;

final class Scheduler implements SchedulerInterface
{
    private const MINUTE_SECONDS = 60;
    private LoopInterface $loop;

    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
    }

    public function schedule(callable $tick): void
    {
        // Tick every 60 seconds
        $this->loop->addPeriodicTimer(self::MINUTE_SECONDS, static function () use ($tick): void {
            $tick();
        });

        // Initial tick because some actions might want to run in this minute
        $tick();
    }
}
