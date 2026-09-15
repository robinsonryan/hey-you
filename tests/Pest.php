<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use RobinsonRyan\HeyYou\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

/**
 * A syntactically valid consumer key for a partyable that is not actually
 * persisted. Consumer models carry UUID7 keys, so a placeholder has to be a
 * UUID — PostgreSQL rejects an integer against a uuid column.
 */
function fakePartyableId(): string
{
    return Str::uuid7()->toString();
}
