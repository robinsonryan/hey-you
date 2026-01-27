<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use RobinsonRyan\HeyYou\Support\AutoIncrementGenerator;

it('creates id column definition', function () {
    $generator = new AutoIncrementGenerator;

    $blueprint = Mockery::mock(Blueprint::class);
    $columnDefinition = Mockery::mock(ColumnDefinition::class);

    $blueprint->shouldReceive('id')
        ->with('id')
        ->once()
        ->andReturn($columnDefinition);

    $generator->columnDefinition($blueprint, 'id');
});

it('returns zero for generate since database handles auto-increment', function () {
    $generator = new AutoIncrementGenerator;

    expect($generator->generate())->toBe(0);
});
