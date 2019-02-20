<?php declare(strict_types=1);

namespace WyriHaximus\React;

use Cron\CronExpression;

final class Action implements ActionInterface
{
    /** @var CronExpression */
    private $expression;

    /** @var callable */
    private $performer;

    /**
     * @param string   $expression
     * @param callable $performer
     */
    public function __construct(string $expression, callable $performer)
    {
        $this->expression = CronExpression::factory($expression);
        $this->performer = $performer;
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
