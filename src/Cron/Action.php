<?php

declare(strict_types=1);

namespace WyriHaximus\React\Cron;

use Cron\CronExpression;
use React\Promise\PromiseInterface;

use function React\Promise\resolve;

final class Action implements ActionInterface
{
    private string $key;

    private CronExpression $expression;

    /** @var callable */
    private $performer;

    public function __construct(string $key, string $expression, callable $performer)
    {
        $this->key        = $key;
        $this->expression = new CronExpression($expression);
        $this->performer  = $performer;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function isDue(): bool
    {
        return $this->expression->isDue();
    }

    public function perform(): PromiseInterface
    {
        return resolve(
            ($this->performer)()
        );
    }
}
