<?php

declare(strict_types=1);

namespace WyriHaximus\React\Cron;

use Cron\CronExpression;

final class Action implements ActionInterface
{
    private string $key;
    private float $mutexTtl;

    private CronExpression $expression;

    /** @var callable */
    private $performer;

    public function __construct(string $key, float $mutexTtl, string $expression, callable $performer)
    {
        $this->key        = $key;
        $this->mutexTtl   = $mutexTtl;
        $this->expression = new CronExpression($expression);
        $this->performer  = $performer;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function mutexTtl(): float
    {
        return $this->mutexTtl;
    }

    public function isDue(): bool
    {
        return $this->expression->isDue();
    }

    public function perform(): void
    {
        ($this->performer)();
    }
}
