<?php

declare(strict_types=1);

use RobinsonRyan\HeyYou\Support\Uuid7Generator;
use RobinsonRyan\HeyYou\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

/**
 * A syntactically valid consumer key for a partyable that is not actually
 * persisted. Consumer models carry UUID7 keys, so a placeholder has to be a
 * UUID — PostgreSQL rejects an integer against a uuid column.
 */
function fakePartyableId(): string
{
    return (new Uuid7Generator)->generate();
}
