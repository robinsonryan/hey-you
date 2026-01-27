<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Events;

use RobinsonRyan\HeyYou\Contracts\EventDispatcher;

final class LaravelEventDispatcher implements EventDispatcher
{
    public function dispatch(object $event): void
    {
        event($event);
    }
}
