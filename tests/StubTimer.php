<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\React\Cron;

use React\EventLoop\TimerInterface;

final readonly class StubTimer implements TimerInterface
{
    /** @param callable $callback */
    public function __construct(
        private float $interval,
        private mixed $callback,
        private bool $periodic,
    ) {
    }

    public function getInterval(): float
    {
        return $this->interval;
    }

    public function getCallback(): callable
    {
        return $this->callback;
    }

    public function isPeriodic(): bool
    {
        return $this->periodic;
    }
}
