<?php

declare(strict_types=1);

use RobinsonRyan\HeyYou\Support\Uuid7Generator;

it('generates a syntactically valid uuid', function (): void {
    $id = (new Uuid7Generator)->generate();

    expect($id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
});

it('generates version 7 uuids', function (): void {
    // The version nibble is the first character of the third group.
    $id = (new Uuid7Generator)->generate();

    expect(explode('-', $id)[2][0])->toBe('7');
});

it('generates distinct values', function (): void {
    $generator = new Uuid7Generator;

    $ids = [];
    for ($i = 0; $i < 50; $i++) {
        $ids[] = $generator->generate();
    }

    expect(array_unique($ids))->toHaveCount(50);
});

it('generates values that sort in creation order', function (): void {
    // The point of v7 over v4: the leading 48 bits are a millisecond timestamp,
    // so lexical order matches insertion order and the index stays append-only.
    $generator = new Uuid7Generator;

    $first = $generator->generate();
    usleep(2000);
    $second = $generator->generate();

    expect(strcmp($second, $first))->toBeGreaterThan(0);
});
