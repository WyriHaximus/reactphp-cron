<?php

declare(strict_types=1);

namespace WyriHaximus\React\Cron;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;

final class Scheduler
{
    private const int TIER_SLOW      = 55;
    private const int TIER_MEDIUM    = 58;
    private const int TIER_FAST      = 59;
    private const int MINUTE_SECONDS = 60;
    private const bool ACTIVE        = true;
    private const bool INACTIVE      = false;

    /** @var callable[] */
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

        if ($currentSecond >= 1 && $currentSecond <= self::TIER_SLOW) {
            $this->timer = Loop::addTimer(self::TIER_SLOW - $currentSecond, function (): void {
                $this->timer = null;
                $this->align();
            });

            return;
        }

        if ($currentSecond > self::TIER_SLOW && $currentSecond <= self::TIER_MEDIUM) {
            $this->timer = Loop::addTimer(1, function (): void {
                $this->timer = null;
                $this->align();
            });

            return;
        }

        if ($currentSecond === self::TIER_FAST) {
            $this->timer = Loop::addTimer(0.001, function (): void {
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
