# Cronlike scheduler running inside a ReactPHP Event Loop

[![Build Status](https://travis-ci.com/WyriHaximus/reactphp-cron.svg?branch=master)](https://travis-ci.com/WyriHaximus/reactphp-cron)
[![Latest Stable Version](https://poser.pugx.org/WyriHaximus/react-cron/v/stable.png)](https://packagist.org/packages/WyriHaximus/react-cron)
[![Total Downloads](https://poser.pugx.org/WyriHaximus/react-cron/downloads.png)](https://packagist.org/packages/WyriHaximus/react-cron)
[![Code Coverage](https://scrutinizer-ci.com/g/WyriHaximus/reactphp-cron/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/WyriHaximus/reactphp-cron/?branch=master)
[![License](https://poser.pugx.org/WyriHaximus/react-cron/license.png)](https://packagist.org/packages/WyriHaximus/react-cron)
[![PHP 7 ready](http://php7ready.timesplinter.ch/WyriHaximus/reactphp-http-middleware-clear-body/badge.svg)](https://travis-ci.org/WyriHaximus/reactphp-http-middleware-clear-body)

# Install

To install via [Composer](http://getcomposer.org/), use the command below, it will automatically detect the latest version and bind it with `^`.

```
composer require wyrihaximus/react-cron
```

# Usage

Schedule actions within the ReactPHP Event Loop

```php
Cron::create(
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
```

# Factory methods

* `Cron::create($loop, ...$actions)`: Cron using basic scheduler ticking every 60 seconds and with in-memory mutex.
* `Cron::createHighPrecision($loop, ...$actions)`: Cron using a high precision scheduler and with in-memory mutex.
* `Cron::createWithMutex($loop, $mutex, ...$actions)`: Cron using basic scheduler ticking every 60 seconds and with supplied mutex.
* `Cron::creatHighPrecisioneWithMutex($loop, $mutex, ...$actions)`: Cron using a high precision scheduler ticking every 60 seconds and with supplied mutex.

# Schedulers 

This package comes with two schedulers, both of which will tick every minute and the moment a new callable is registered to it:

* Basic scheduler: Will tick every 60 seconds regardless of what second of the minute it is.
* High precision scheduler: Will ALWAYS tick in the first second of the minute, it will readjust when it deviates out of 
that first second to in the first second next minute.

# Mutex

All mutexes must implement [`wyrihaximus/react-mutex`](https://packagist.org/packages/wyrihaximus/react-mutex) to provide 
additional implementations beyond the default in memory one. This is meant to do distributed locking of cron jobs.

# License

The MIT License (MIT)

Copyright (c) 2019 Cees-Jan Kiewiet

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
