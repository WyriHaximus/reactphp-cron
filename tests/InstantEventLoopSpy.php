<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\React\Cron;

use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

final class InstantEventLoopSpy implements LoopInterface
{
    private LoopInterface $loop;

    public function __construct()
    {
        $this->loop = Loop::get();
    }

    public function addReadStream($stream, $listener)
    {
        $this->loop->addReadStream($stream, $listener);
    }

    public function addWriteStream($stream, $listener)
    {
        $this->loop->addWriteStream($stream, $listener);
    }

    public function removeReadStream($stream)
    {
        $this->loop->removeReadStream($stream);
    }

    public function removeWriteStream($stream)
    {
        $this->loop->removeWriteStream($stream);
    }

    public function addTimer($interval, $callback)
    {
        if ($interval !== 300) {
            Loop::futureTick($callback);
        }
        return $this->loop->addTimer($interval, static function (): void {});
    }

    public function addPeriodicTimer($interval, $callback)
    {
        Loop::futureTick($callback);
        return $this->loop->addPeriodicTimer($interval, static function (): void {});
    }

    public function cancelTimer(TimerInterface $timer)
    {
        $this->loop->cancelTimer($timer);
    }

    public function futureTick($listener)
    {
        $this->loop->futureTick($listener);
    }

    public function addSignal($signal, $listener)
    {
        $this->loop->addSignal($signal, $listener);
    }

    public function removeSignal($signal, $listener)
    {
        $this->loop->removeSignal($signal, $listener);
    }

    public function run()
    {
        $this->loop->run();
    }

    public function stop()
    {
        $this->loop->stop();
    }
}
