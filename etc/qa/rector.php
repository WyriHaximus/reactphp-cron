<?php

declare(strict_types=1);

use WyriHaximus\RectorPHP\RectorConfig;
use WyriHaximus\Tests\React\Cron\EventLoopStub;
use WyriHaximus\Tests\React\Cron\StubTimer;

return RectorConfig::configure(dirname(__DIR__, 2))
    ->withSkip([
        EventLoopStub::class,
        StubTimer::class,
    ]);
