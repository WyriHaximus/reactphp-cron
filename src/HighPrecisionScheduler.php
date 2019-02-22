<?php declare(strict_types=1);

namespace WyriHaximus\React;

use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

final class HighPrecisionScheduler implements SchedulerInterface
{
    /** @var LoopInterface */
    private $loop;

    /** @var bool */
    private $useHighResolution = false;

    /** @var callable[] */
    private $ticks = [];

    /** @var TimerInterface */
    private $timer;

    /**
     * @param LoopInterface $loop
     */
    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
        $this->useHighResolution = \function_exists('hrtime');

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
        return $this->useHighResolution ? \hrtime(true) * 1e-9 : \microtime(true);
    }

    private function hasDrifted(float $time): bool
    {
        return (int)\date('s', (int)$time) > 0;
    }

    private function tick(): void
    {
        $startOfTick = $this->time();

        foreach ($this->ticks as $tick) {
            $tick();
        }

        if ($this->hasDrifted($startOfTick)) {
            $this->align();
        }
    }

    private function align(): void
    {
        if ($this->timer instanceof TimerInterface) {
            $this->loop->cancelTimer($this->timer);
        }

        $currentSecond = (int)\date('s', (int)$this->time());

        if ($currentSecond >= 1 && $currentSecond <= 55) {
            $this->loop->addTimer(55 - $currentSecond, function (): void {
                $this->align();
            });

            return;
        }

        if ($currentSecond > 55 && $currentSecond <= 58) {
            $this->loop->addTimer(1, function (): void {
                $this->align();
            });

            return;
        }

        if ($currentSecond === 59) {
            $this->loop->addTimer(0.001, function (): void {
                $this->align();
            });

            return;
        }

        $this->tick();

        $this->timer = $this->loop->addPeriodicTimer(60, function (): void {
            $this->tick();
        });
    }
}
