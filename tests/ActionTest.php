<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\React\Cron;

use WyriHaximus\AsyncTestUtilities\AsyncTestCase;
use WyriHaximus\React\Cron\Action;
use WyriHaximus\React\Cron\ActionInterface;
use WyriHaximus\React\Cron\RunOnStartUpAction;

final class ActionTest extends AsyncTestCase
{
    /** @return iterable<string, array<class-string<ActionInterface>>> */
    public static function actionProvider(): iterable
    {
        yield 'action' => [Action::class];
        yield 'runs-on-startup-action' => [RunOnStartUpAction::class];
    }

    /**
     * @param class-string<ActionInterface> $actionClass
     *
     * @dataProvider actionProvider
     * @test
     */
    public function isDue(string $actionClass): void
    {
        $action = new $actionClass('name', 0.1, '* * * * *', static function (): void {
        });

        self::assertTrue($action->isDue());
    }

    /**
     * @param class-string<ActionInterface> $actionClass
     *
     * @dataProvider actionProvider
     * @test
     */
    public function perform(string $actionClass): void
    {
        $ran    = false;
        $action = new $actionClass('name', 0.1, '* * * * *', static function () use (&$ran): void {
            $ran = true;
        });

        $action->perform();

        self::assertTrue($ran);
    }
}
