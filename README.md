# Cronlike scheduler running inside a ReactPHP Event Loop

![Continuous Integration](https://github.com/WyriHaximus/reactphp-cron/workflows/Continuous%20Integration/badge.svg)
[![Latest Stable Version](https://poser.pugx.org/WyriHaximus/react-cron/v/stable.png)](https://packagist.org/packages/WyriHaximus/react-cron)
[![Total Downloads](https://poser.pugx.org/WyriHaximus/react-cron/downloads.png)](https://packagist.org/packages/WyriHaximus/react-cron)
[![Code Coverage](https://scrutinizer-ci.com/g/WyriHaximus/reactphp-cron/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/WyriHaximus/reactphp-cron/?branch=master)
[![License](https://poser.pugx.org/WyriHaximus/react-cron/license.png)](https://packagist.org/packages/WyriHaximus/react-cron)

# Install

To install via [Composer](http://getcomposer.org/), use the command below, it will automatically detect the latest version and bind it with `^`.

```
composer require wyrihaximus/react-cron
```

# Usage

Schedule actions within the ReactPHP Event Loop

```php
use React\EventLoop\Factory;
use React\Promise\PromiseInterface;
use WyriHaximus\React\Cron;
use WyriHaximus\React\Cron\Action;

use function React\Promise\resolve;

$loop = Factory::create();

$cron = Cron::create(
    $loop,
    new Action(
        'Hour', // Identifier used for mutex locking locking
        60, // TTL for the mutex lock, always set this way higher than the expected execution time, but low enough any failures during the run will cause issues
        '@hourly', // The cron expression used to schedule this action
        function (): PromiseInterface { // The callable ran when this action is due according to it's schedule
            echo 'Another hour has passed!', PHP_EOL;

            return resolve(true); // This callable MUST return a promise, which is used for releasing the mutex lock
        }
    ),
    new Action(
        'Minute',
        0.1,
        '* * * * *',
        function (): PromiseInterface {
            echo 'Another minute has passed!', PHP_EOL;

            return resolve(true);
        }
    )
);

// Stops scheduling new action runs
$cron->stop();
```

# Factory methods

* `Cron::create($loop, ...$actions)`: Cron with in-memory mutex.
* `Cron::createWithMutex($loop, $mutex, ...$actions)`: Cron with supplied mutex.

# Mutex

All mutexes must implement [`wyrihaximus/react-mutex`](https://packagist.org/packages/wyrihaximus/react-mutex) to provide
additional implementations beyond the default in memory one. This is meant to do distributed locking of cron jobs.

# License

The MIT License (MIT)

Copyright (c) 2021 Cees-Jan Kiewiet

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
