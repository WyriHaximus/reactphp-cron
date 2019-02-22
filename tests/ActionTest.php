<?php declare(strict_types=1);

namespace WyriHaximus\Tests\React;

use ApiClients\Tools\TestUtilities\TestCase;
use WyriHaximus\React\Action;

/**
 * @internal
 */
final class ActionTest extends TestCase
{
    public function testIsDue(): void
    {
        $action = new Action('name', '* * * * *', function (): void {
        });

        self::assertTrue($action->isDue());
    }

    public function testPerform(): void
    {
        $ran = false;
        $action = new Action('name', '* * * * *', function () use (&$ran): void {
            $ran = true;
        });

        $action->perform();

        self::assertTrue($ran);
    }
}
