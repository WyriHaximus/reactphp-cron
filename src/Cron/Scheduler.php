<?php

declare(strict_types=1);

namespace WyriHaximus\React\Cron;

use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

use function hrtime;
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

    private LoopInterface $loop;

    /** @var callable[] */
    private array $ticks = [];

    private ?TimerInterface $timer = null;
    private bool $active           = self::ACTIVE;

    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;

        $this->align();
    }

    public function schedule(callable $tick): void
    {
        // Push this new tick on the stack with the rest, running it in the next minute
        $this->ticks[] = $tick;

        // Initial tick because some actions might want to run in this minute
        $tick();
    }

    private function time(): float
    {
        return hrtime(TRUE_) * 1.0E-9;
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
            $this->loop->cancelTimer($this->timer);
            $this->timer = null;
        }

        $currentSecond = (int) date('s', (int) $this->time());

        if ($currentSecond >= ONE && $currentSecond <= self::TIER_SLOW) {
            $this->timer = $this->loop->addTimer(self::TIER_SLOW - $currentSecond, function (): void {
                $this->timer = null;
                $this->align();
            });

            return;
        }

        if ($currentSecond > self::TIER_SLOW && $currentSecond <= self::TIER_MEDIUM) {
            $this->timer = $this->loop->addTimer(ONE, function (): void {
                $this->timer = null;
                $this->align();
            });

            return;
        }

        if ($currentSecond === self::TIER_FAST) {
            $this->timer = $this->loop->addTimer(0.001, function (): void {
                $this->timer = null;
                $this->align();
            });

            return;
        }

        $this->tick();

        $this->timer = $this->loop->addPeriodicTimer(self::MINUTE_SECONDS, function (): void {
            $this->tick();
        });
    }

    public function stop(): void
    {
        $this->active = self::INACTIVE;

        if (! ($this->timer instanceof TimerInterface)) {
            return;
        }

        $this->loop->cancelTimer($this->timer);
    }
}
