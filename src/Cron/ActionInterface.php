<?php

declare(strict_types=1);

namespace WyriHaximus\React\Cron;

interface ActionInterface
{
    /**
     * Identifiable name for this action.
     */
    public function key(): string;

    /**
     * Ttl for the mutex used to ensure we only have one of each action running at the same time.
     * The action must have been finished within this time frame.
     */
    public function mutexTtl(): float;

    /**
     * Returns true when it is time for this action to run, false when it isn't.
     */
    public function isDue(): bool;

    /**
     * Run the action, throwing when it is for whatever reason unsuccessful.
     */
    public function perform(): void;
}
