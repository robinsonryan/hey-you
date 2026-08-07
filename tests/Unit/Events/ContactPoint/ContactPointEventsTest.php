<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointBounced;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointCreated;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointDeleted;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointMarkedUnreachable;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointPurposeAttached;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointPurposeDetached;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointRestored;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointUpdated;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointVerificationExpired;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointVerificationFailed;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointVerified;
use RobinsonRyan\HeyYou\Models\ContactPoint;
use RobinsonRyan\HeyYou\Models\Party;
use RobinsonRyan\HeyYou\Tests\Fixtures\Models\User;

beforeEach(function (): void {
    $this->user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);
    $this->party = $this->user->party;
    $this->contactPoint = $this->party->contactPoints()->create([
        'channel' => 'email',
        'value_raw' => 'test@example.com',
    ]);
});

describe('ContactPointCreated', function (): void {
    it('contains the contact point and party', function (): void {
        $event = new ContactPointCreated($this->contactPoint, $this->party);

        expect($event->contactPoint)->toBeInstanceOf(ContactPoint::class)
            ->and($event->party)->toBeInstanceOf(Party::class)
            ->and($event->party->id)->toBe($this->party->id);
    });
});

describe('ContactPointUpdated', function (): void {
    it('contains the contact point, party, and changed attributes', function (): void {
        $changedAttributes = ['value_raw' => 'new@example.com'];
        $event = new ContactPointUpdated($this->contactPoint, $this->party, $changedAttributes);

        expect($event->contactPoint)->toBeInstanceOf(ContactPoint::class)
            ->and($event->party)->toBeInstanceOf(Party::class)
            ->and($event->changedAttributes)->toBe($changedAttributes);
    });
});

describe('ContactPointDeleted', function (): void {
    it('contains the contact point and party', function (): void {
        $event = new ContactPointDeleted($this->contactPoint, $this->party);

        expect($event->contactPoint)->toBeInstanceOf(ContactPoint::class)
            ->and($event->party)->toBeInstanceOf(Party::class);
    });
});

describe('ContactPointVerified', function (): void {
    it('contains the contact point, method, and timestamp', function (): void {
        $verifiedAt = Carbon::now();
        $event = new ContactPointVerified($this->contactPoint, 'code', $verifiedAt);

        expect($event->contactPoint)->toBeInstanceOf(ContactPoint::class)
            ->and($event->method)->toBe('code')
            ->and($event->verifiedAt)->toBe($verifiedAt);
    });
});

describe('ContactPointRestored', function (): void {
    it('contains the contact point and party', function (): void {
        $event = new ContactPointRestored($this->contactPoint, $this->party);

        expect($event->contactPoint)->toBeInstanceOf(ContactPoint::class)
            ->and($event->party)->toBeInstanceOf(Party::class);
    });
});

describe('ContactPointVerificationFailed', function (): void {
    it('contains the contact point, method, and reason', function (): void {
        $event = new ContactPointVerificationFailed($this->contactPoint, 'code', 'Invalid verification code');

        expect($event->contactPoint)->toBeInstanceOf(ContactPoint::class)
            ->and($event->method)->toBe('code')
            ->and($event->reason)->toBe('Invalid verification code');
    });
});

describe('ContactPointVerificationExpired', function (): void {
    it('contains the contact point', function (): void {
        $event = new ContactPointVerificationExpired($this->contactPoint);

        expect($event->contactPoint)->toBeInstanceOf(ContactPoint::class);
    });
});

describe('ContactPointBounced', function (): void {
    it('contains the contact point and bounce info', function (): void {
        $bounceInfo = ['type' => 'hard', 'message' => 'Address not found'];
        $event = new ContactPointBounced($this->contactPoint, $bounceInfo);

        expect($event->contactPoint)->toBeInstanceOf(ContactPoint::class)
            ->and($event->bounceInfo)->toBe($bounceInfo);
    });
});

describe('ContactPointMarkedUnreachable', function (): void {
    it('contains the contact point and reason', function (): void {
        $event = new ContactPointMarkedUnreachable($this->contactPoint, 'Multiple failed delivery attempts');

        expect($event->contactPoint)->toBeInstanceOf(ContactPoint::class)
            ->and($event->reason)->toBe('Multiple failed delivery attempts');
    });
});

describe('ContactPointPurposeAttached', function (): void {
    it('contains the contact point, purpose, and attributes', function (): void {
        $attributes = ['priority' => 1, 'is_preferred' => true];
        $event = new ContactPointPurposeAttached($this->contactPoint, 'billing', $attributes);

        expect($event->contactPoint)->toBeInstanceOf(ContactPoint::class)
            ->and($event->purpose)->toBe('billing')
            ->and($event->attributes)->toBe($attributes);
    });
});

describe('ContactPointPurposeDetached', function (): void {
    it('contains the contact point and purpose', function (): void {
        $event = new ContactPointPurposeDetached($this->contactPoint, 'billing');

        expect($event->contactPoint)->toBeInstanceOf(ContactPoint::class)
            ->and($event->purpose)->toBe('billing');
    });
});
