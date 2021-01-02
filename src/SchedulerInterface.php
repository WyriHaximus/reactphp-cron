<?php

declare(strict_types=1);

namespace WyriHaximus\React;

interface SchedulerInterface
{
    /**
     * Schedule the execution of the passed callable once every minute.
     */
    public function schedule(callable $tick): void;
}
