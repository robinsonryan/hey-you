<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointCreated;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointDeleted;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointUpdated;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointVerified;
use RobinsonRyan\HeyYou\Models\ContactPoint;
use RobinsonRyan\HeyYou\Models\Party;
use RobinsonRyan\HeyYou\Tests\Fixtures\Models\User;

beforeEach(function () {
    $this->user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);
    $this->party = $this->user->party;
    $this->contactPoint = $this->party->contactPoints()->create([
        'channel' => 'email',
        'value_raw' => 'test@example.com',
    ]);
});

describe('ContactPointCreated', function () {
    it('contains the contact point and party', function () {
        $event = new ContactPointCreated($this->contactPoint, $this->party);

        expect($event->contactPoint)->toBeInstanceOf(ContactPoint::class)
            ->and($event->party)->toBeInstanceOf(Party::class)
            ->and($event->party->id)->toBe($this->party->id);
    });
});

describe('ContactPointUpdated', function () {
    it('contains the contact point, party, and changed attributes', function () {
        $changedAttributes = ['value_raw' => 'new@example.com'];
        $event = new ContactPointUpdated($this->contactPoint, $this->party, $changedAttributes);

        expect($event->contactPoint)->toBeInstanceOf(ContactPoint::class)
            ->and($event->party)->toBeInstanceOf(Party::class)
            ->and($event->changedAttributes)->toBe($changedAttributes);
    });
});

describe('ContactPointDeleted', function () {
    it('contains the contact point and party', function () {
        $event = new ContactPointDeleted($this->contactPoint, $this->party);

        expect($event->contactPoint)->toBeInstanceOf(ContactPoint::class)
            ->and($event->party)->toBeInstanceOf(Party::class);
    });
});

describe('ContactPointVerified', function () {
    it('contains the contact point, method, and timestamp', function () {
        $verifiedAt = Carbon::now();
        $event = new ContactPointVerified($this->contactPoint, 'code', $verifiedAt);

        expect($event->contactPoint)->toBeInstanceOf(ContactPoint::class)
            ->and($event->method)->toBe('code')
            ->and($event->verifiedAt)->toBe($verifiedAt);
    });
});
