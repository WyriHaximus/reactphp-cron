<?php declare(strict_types=1);

namespace WyriHaximus\Tests\React;

use ApiClients\Tools\TestUtilities\TestCase;
use Prophecy\Argument;
use React\EventLoop\LoopInterface;
use WyriHaximus\React\Action;
use WyriHaximus\React\Cron;

/**
 * @internal
 */
final class CronTest extends TestCase
{
    public function testScheduling(): void
    {
        $loop = $this->prophesize(LoopInterface::class);
        $loop->addPeriodicTimer(60, Argument::type('callable'))->shouldBeCalled();

        $ran = false;
        $action = new Action('* * * * *', function () use (&$ran): void {
            $ran = true;
        });

        Cron::create($loop->reveal(), $action);

        self::assertTrue($ran);
    }
}
