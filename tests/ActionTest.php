<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\React;

use WyriHaximus\AsyncTestUtilities\AsyncTestCase;
use WyriHaximus\React\Action;

final class ActionTest extends AsyncTestCase
{
    public function testIsDue(): void
    {
        $action = new Action('name', '* * * * *', static function (): void {
        });

        self::assertTrue($action->isDue());
    }

    public function testPerform(): void
    {
        $ran    = false;
        $action = new Action('name', '* * * * *', static function () use (&$ran): void {
            $ran = true;
        });

        $action->perform();

        self::assertTrue($ran);
    }
}
