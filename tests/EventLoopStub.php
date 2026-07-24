<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\React\Cron;

use Lcobucci\Clock\FrozenClock;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use RuntimeException;
use SplQueue;

use function array_any;
use function array_column;
use function call_user_func;
use function min;
use function spl_object_id;

final class EventLoopStub implements LoopInterface
{
    /** @var SplQueue<callable> */
    private readonly SplQueue $futureTickQueue;

    /** @var array<int, array{timer: StubTimer, deadline: float}> */
    private array $timers = [];

    private bool $running = false;

    public function __construct(
        public readonly FrozenClock $clock,
    ) {
        $this->futureTickQueue = new SplQueue();
    }

    public function now(): float
    {
        return (float) $this->clock->now()->format('U.u');
    }

    public function tick(): void
    {
        do {
            $this->runFutureTicks();
            $this->runDueTimers();
        } while ($this->futureTickQueue->count() > 0 || $this->hasDueTimers());
    }

    /** @param non-empty-string $modifier */
    public function adjustClock(string $modifier): void
    {
        $this->clock->adjustTime($modifier);
    }

    /**
     * @param resource $stream
     * @param callable $listener
     */
    public function addReadStream($stream, $listener): void
    {
        throw new RuntimeException('Not implemented');
    }

    /**
     * @param resource $stream
     * @param callable $listener
     */
    public function addWriteStream($stream, $listener): void
    {
        throw new RuntimeException('Not implemented');
    }

    /** @param resource $stream */
    public function removeReadStream($stream): void
    {
    }

    /** @param resource $stream */
    public function removeWriteStream($stream): void
    {
    }

    /**
     * @param int|float $interval
     * @param callable  $callback
     */
    public function addTimer($interval, $callback): TimerInterface
    {
        $timer = new StubTimer($interval, $callback, false);
        $this->scheduleTimer($timer, $this->now() + $interval);

        return $timer;
    }

    /**
     * @param int|float $interval
     * @param callable  $callback
     */
    public function addPeriodicTimer($interval, $callback): TimerInterface
    {
        $timer = new StubTimer($interval, $callback, true);
        $this->scheduleTimer($timer, $this->now() + $interval);

        return $timer;
    }

    public function cancelTimer(TimerInterface $timer): void
    {
        unset($this->timers[spl_object_id($timer)]);
    }

    /** @param callable $listener */
    public function futureTick($listener): void
    {
        $this->futureTickQueue->enqueue($listener);
    }

    /**
     * @param int      $signal
     * @param callable $listener
     */
    public function addSignal($signal, $listener): void
    {
        throw new RuntimeException('Not implemented');
    }

    /**
     * @param int      $signal
     * @param callable $listener
     */
    public function removeSignal($signal, $listener): void
    {
    }

    public function run(): void
    {
        $this->running = true;

        while ($this->running) {
            $this->tick();

            if ($this->futureTickQueue->count() === 0 && $this->timers === []) {
                $this->stop();

                return;
            }

            if ($this->hasDueTimers()) {
                continue;
            }

            $nextDeadline = $this->getNextTimerDeadline();
            if ($nextDeadline === null) {
                $this->stop();

                return;
            }

            $delta = $nextDeadline - $this->now();
            if ($delta <= 0) {
                continue;
            }

            $this->clock->adjustTime('+' . $delta . ' seconds');
        }
    }

    public function stop(): void
    {
        $this->running = false;
    }

    private function getNextTimerDeadline(): float|null
    {
        if ($this->timers === []) {
            return null;
        }

        return min(array_column($this->timers, 'deadline'));
    }

    private function runFutureTicks(): void
    {
        $count = $this->futureTickQueue->count();

        while ($count-- > 0) {
            call_user_func($this->futureTickQueue->dequeue());
        }
    }

    private function hasDueTimers(): bool
    {
        $now = $this->now();

        return array_any($this->timers, static fn (array $scheduled): bool => $scheduled['deadline'] <= $now);
    }

    private function scheduleTimer(StubTimer $timer, float $deadline): void
    {
        $this->timers[spl_object_id($timer)] = [
            'timer'    => $timer,
            'deadline' => $deadline,
        ];
    }

    private function runDueTimers(): void
    {
        while (true) {
            $now   = $this->now();
            $dueId = null;
            $due   = null;

            foreach ($this->timers as $id => $scheduled) {
                if ($scheduled['deadline'] > $now) {
                    continue;
                }

                if ($due !== null && $scheduled['deadline'] >= $due) {
                    continue;
                }

                $due   = $scheduled['deadline'];
                $dueId = $id;
            }

            if ($dueId === null) {
                return;
            }

            $scheduled = $this->timers[$dueId];
            $timer     = $scheduled['timer'];

            unset($this->timers[$dueId]);

            call_user_func($timer->getCallback(), $timer);

            if (! $timer->isPeriodic()) {
                continue;
            }

            $this->scheduleTimer($timer, $this->now() + $timer->getInterval());
        }
    }
}
