<?php

declare(strict_types=1);

use React\EventLoop\Loop;
use WyriHaximus\Tests\React\Cron\InstantEventLoopSpy;

Loop::set(new InstantEventLoopSpy());
