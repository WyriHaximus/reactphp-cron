<?php

declare(strict_types=1);

namespace WyriHaximus\React\Cron;

use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;

use function microtime;
use function Safe\date;

use const WyriHaximus\Constants\Boolean\TRUE_;
use const WyriHaximus\Constants\Numeric\ONE;
use const WyriHaximus\Constants\Numeric\ZERO;

final class Scheduler
{
    private const TIER_SLOW      = 55;
    private const TIER_MEDIUM    = 58;
    private const TIER_FAST      = 59;
    private const MINUTE_SECONDS = 60;
    private const ACTIVE         = true;
    private const INACTIVE       = false;

    /** @var callable[] */
    private array $ticks = [];

    private TimerInterface|null $timer = null;
    private bool $active               = self::ACTIVE;

    public function __construct()
    {
        $this->align();
    }

    public function schedule(callable $tick): void
    {
        // Push this new tick on the stack with the rest, running it in the next minute
        $this->ticks[] = $tick;
    }

    private function time(): float
    {
        return microtime(TRUE_);
    }

    private function hasDrifted(float $time): bool
    {
        return date('s', (int) $time) > ZERO;
    }

    private function tick(): void
    {
        if ($this->active === self::INACTIVE) {
            return;
        }

        $startOfTick = $this->time();

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

        $currentSecond = (int) date('s', (int) $this->time());

        if ($currentSecond >= ONE && $currentSecond <= self::TIER_SLOW) {
            $this->timer = Loop::addTimer(self::TIER_SLOW - $currentSecond, function (): void {
                $this->timer = null;
                $this->align();
            });

            return;
        }

        if ($currentSecond > self::TIER_SLOW && $currentSecond <= self::TIER_MEDIUM) {
            $this->timer = Loop::addTimer(ONE, function (): void {
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
