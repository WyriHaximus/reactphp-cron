<?php

declare(strict_types=1);

namespace WyriHaximus\React;

use React\Promise\PromiseInterface;

interface ActionInterface
{
    /**
     * Identifiable name for this action.
     */
    public function key(): string;

    /**
     * Returns true when it is time for this action to run, false when it isn't.
     */
    public function isDue(): bool;

    /**
     * Run the action returning a promise which should resolve when the action has been completed.
     */
    public function perform(): PromiseInterface;
}
