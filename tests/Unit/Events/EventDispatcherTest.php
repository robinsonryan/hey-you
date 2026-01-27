<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RobinsonRyan\HeyYou\Contracts\EventDispatcher;
use RobinsonRyan\HeyYou\Events\LaravelEventDispatcher;

beforeEach(function () {
    Event::fake();
});

it('implements the EventDispatcher contract', function () {
    $dispatcher = new LaravelEventDispatcher;

    expect($dispatcher)->toBeInstanceOf(EventDispatcher::class);
});

it('dispatches events using Laravel event system', function () {
    $dispatcher = new LaravelEventDispatcher;

    $event = new class
    {
        public string $name = 'test';
    };

    $dispatcher->dispatch($event);

    Event::assertDispatched(get_class($event));
});

it('is bound in the container', function () {
    $dispatcher = app(EventDispatcher::class);

    expect($dispatcher)->toBeInstanceOf(LaravelEventDispatcher::class);
});
