<?php

declare(strict_types=1);

namespace WyriHaximus\React\Cron;

use Cron\CronExpression;

final readonly class Action implements ActionInterface
{
    private CronExpression $expression;

    /** @var callable */
    private mixed $performer;

    public function __construct(private string $key, private float $mutexTtl, string $expression, callable $performer)
    {
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
