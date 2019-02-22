<?php declare(strict_types=1);

namespace WyriHaximus\React;

use Cron\CronExpression;
use React\Promise\PromiseInterface;
use function React\Promise\resolve;

final class Action implements ActionInterface
{
    /** @var string */
    private $key;

    /** @var CronExpression */
    private $expression;

    /** @var callable */
    private $performer;

    /**
     * @param string   $key
     * @param string   $expression
     * @param callable $performer
     */
    public function __construct(string $key, string $expression, callable $performer)
    {
        $this->key = $key;
        $this->expression = CronExpression::factory($expression);
        $this->performer = $performer;
    }

    public function getKey(): string
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
