<?php

declare(strict_types=1);

namespace WyriHaximus\React\Cron;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;

final class Scheduler
{
    private const int TIER_SLOW                          = 55;
    private const int TIER_MEDIUM                        = 58;
    private const int TIER_FAST                          = 59;
    private const float INTERVAL_SLOW_TO_MEDIUM          = 1.3;
    private const float INTERVAL_MEDIUM_TO_FAST          = 0.13;
    private const float INTERVAL_FAST                    = 0.001;
    private const int MINUTE_SECONDS                     = 60;
    private const bool ACTIVE                            = true;
    private const bool INACTIVE                          = false;
    private const int FIRST_SECOND_AFTER_OUR_TICK_WINDOW = 1;

    /** @var array<callable> */
    private array $ticks = [];

    private TimerInterface|null $timer = null;
    private bool $active               = self::ACTIVE;

    public function __construct(
        private readonly ClockInterface $clock,
    ) {
        $this->align();
    }

    public function schedule(callable $tick): void
    {
        // Push this new tick on the stack with the rest, running it in the next minute
        $this->ticks[] = $tick;
    }

    private function hasDrifted(DateTimeImmutable $time): bool
    {
        return (int) $time->format('s') > 0;
    }

    private function tick(): void
    {
        if ($this->active === self::INACTIVE) {
            return;
        }

        $startOfTick = $this->clock->now();

        foreach ($this->ticks as $tick) {
            $tick();
        }

        if (! $this->hasDrifted($startOfTick)) {
            return;
        }

        $this->align();
    }

    private function align(): void
    {
        if ($this->timer instanceof TimerInterface) {
            Loop::cancelTimer($this->timer);
            $this->timer = null;
        }

        $currentSecond = (int) $this->clock->now()->format('s');

        if ($currentSecond >= self::FIRST_SECOND_AFTER_OUR_TICK_WINDOW && $currentSecond <= self::TIER_SLOW) {
            $this->timer = Loop::addTimer(self::INTERVAL_SLOW_TO_MEDIUM, function (): void {
                $this->timer = null;
                $this->align();
            });

            return;
        }

        if ($currentSecond > self::TIER_SLOW && $currentSecond <= self::TIER_MEDIUM) {
            $this->timer = Loop::addTimer(self::INTERVAL_MEDIUM_TO_FAST, function (): void {
                $this->timer = null;
                $this->align();
            });

            return;
        }

        if ($currentSecond === self::TIER_FAST) {
            $this->timer = Loop::addTimer(self::INTERVAL_FAST, function (): void {
                $this->timer = null;
                $this->align();
            });

            return;
        }

        $this->tick();

        $this->timer = Loop::addPeriodicTimer(self::MINUTE_SECONDS, function (): void {
            $this->tick();
        });
    }

    public function stop(): void
    {
        $this->active = self::INACTIVE;

        if (! ($this->timer instanceof TimerInterface)) {
            return;
        }

        Loop::cancelTimer($this->timer);
    }
}
