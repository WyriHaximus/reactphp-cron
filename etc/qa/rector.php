<?php

declare(strict_types=1);

use WyriHaximus\Tests\React\Cron\EventLoopStub;
use WyriHaximus\Tests\React\Cron\StubTimer;
use WyriHaximus\TestUtilities\RectorConfig;

return RectorConfig::configure(dirname(__DIR__, 2))
    ->withSkip([
        EventLoopStub::class,
        StubTimer::class,
    ]);
