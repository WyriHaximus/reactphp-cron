<?php

use React\EventLoop\Factory;
use React\Promise\PromiseInterface;
use function React\Promise\resolve;
use WyriHaximus\React\Action;
use WyriHaximus\React\Cron;

require 'vendor/autoload.php';

$loop = Factory::create();

Cron::createHighPrecision(
    $loop,
    new Action(
        'Hour', // Identifier used for mutex locking locking
        '@hourly', // The cron expression used to schedule this action
        function (): PromiseInterface { // The callable ran when this action is due according to it's schedule
            echo 'Another hour has passed!', PHP_EOL;

            return resolve(true); // This callable MUST return a promise, which is used for releasing the mutex lock
        }
    ),
    new Action(
        'Minute',
        '* * * * *',
        function (): PromiseInterface {
            echo 'Another minute has passed!', PHP_EOL;

            return resolve(true);
        }
    )
);

$loop->run();
