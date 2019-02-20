<?php declare(strict_types=1);

namespace WyriHaximus\React;

interface ActionInterface
{
    /**
     * Returns true when it is time for this action to run, false when it isn't.
     *
     * @return bool
     */
    public function isDue(): bool;

    /**
     * Run the action.
     */
    public function perform(): void;
}
