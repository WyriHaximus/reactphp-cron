<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\React\Cron;

use WyriHaximus\AsyncTestUtilities\AsyncTestCase;
use WyriHaximus\React\Cron\Action;

final class ActionTest extends AsyncTestCase
{
    public function testIsDue(): void
    {
        $action = new Action('name', 0.1, '* * * * *', static function (): void {
        });

        self::assertTrue($action->isDue());
    }

    public function testPerform(): void
    {
        $ran    = false;
        $action = new Action('name', 0.1, '* * * * *', static function () use (&$ran): void {
            $ran = true;
        });

        $action->perform();

        self::assertTrue($ran);
    }
}
