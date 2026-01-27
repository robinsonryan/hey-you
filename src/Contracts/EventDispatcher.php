<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Contracts;

interface EventDispatcher
{
    public function dispatch(object $event): void;
}
