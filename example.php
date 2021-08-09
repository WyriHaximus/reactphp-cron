<?php

use React\EventLoop\Factory;
use React\EventLoop\Loop;
use React\Promise\PromiseInterface;
use function React\Promise\resolve;
use WyriHaximus\React\Cron;

require 'vendor/autoload.php';

Cron::create(
    new Cron\Action(
        'Hour', // Identifier used for mutex locking locking
        1, // TTL for Mutex Locking (usually a few times the maximum time this cron should take)
        '@hourly', // The cron expression used to schedule this action
        function (): PromiseInterface { // The callable ran when this action is due according to it's schedule
            echo 'Another hour has passed!', PHP_EOL;

            return resolve(true); // This callable MUST return a promise, which is used for releasing the mutex lock
        }
    ),
    new Cron\Action(
        'Minute',
        300,
        '* * * * *',
        function (): PromiseInterface {
            echo 'Another minute has passed!', PHP_EOL;

            return resolve(true);
        }
    )
);

Loop::get()->run();
