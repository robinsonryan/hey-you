<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointVerificationExpired;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointVerificationFailed;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointVerified;
use RobinsonRyan\HeyYou\Models\ContactPoint;
use RobinsonRyan\HeyYou\Models\Party;
use RobinsonRyan\HeyYou\Models\VerificationEvent;
use RobinsonRyan\HeyYou\Tests\Fixtures\Models\User;

beforeEach(function (): void {
    $this->party = Party::create([
        'partyable_type' => User::class,
        'partyable_id' => fakePartyableId(),
        'display_name_cached' => 'John Doe',
    ]);

    $this->contactPoint = ContactPoint::create([
        'party_id' => $this->party->id,
        'channel' => 'email',
        'value_raw' => 'test@example.com',
    ]);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function verificationHistoryCount(ContactPoint $contactPoint): int
{
    return $contactPoint->verificationEvents()->count();
}

/**
 * History for a contact point, oldest first. `initiated_at` is a second-precision
 * column, so the time-ordered UUID7 key breaks ties within the same second.
 *
 * @return Illuminate\Database\Eloquent\Collection<int, VerificationEvent>
 */
function verificationHistoryRows(ContactPoint $contactPoint): Illuminate\Database\Eloquent\Collection
{
    return $contactPoint->verificationEvents()->orderBy('initiated_at')->orderBy('id')->get();
}

describe('startVerification', function (): void {
    it('records a pending row with an open completed_at', function (): void {
        Carbon::setTestNow('2026-08-08 12:00:00');

        $event = $this->contactPoint->startVerification('code');

        expect($event)->toBeInstanceOf(VerificationEvent::class)
            ->and($event->status)->toBe(VerificationEvent::STATUS_PENDING)
            ->and($event->method)->toBe('code')
            ->and($event->contact_point_id)->toBe($this->contactPoint->id)
            ->and($event->initiated_at->toDateTimeString())->toBe('2026-08-08 12:00:00')
            ->and($event->completed_at)->toBeNull()
            ->and($event->expires_at)->toBeNull()
            ->and(verificationHistoryCount($this->contactPoint))->toBe(1);
    });

    it('records supplied evidence', function (): void {
        $event = $this->contactPoint->startVerification('link', ['token_hash' => 'abc123']);

        expect($event->evidence)->toBe(['token_hash' => 'abc123']);
    });

    it('records null evidence when none is supplied', function (): void {
        $event = $this->contactPoint->startVerification('code');

        expect($event->evidence)->toBeNull();
    });

    it('returns null and writes nothing when history logging is disabled', function (): void {
        config()->set('heyyou.verification.log_history', false);

        $event = $this->contactPoint->startVerification('code');

        expect($event)->toBeNull()
            ->and(verificationHistoryCount($this->contactPoint))->toBe(0);
    });
});

describe('markVerified', function (): void {
    it('sets the verification attributes and persists them', function (): void {
        Carbon::setTestNow('2026-08-08 12:00:00');
        $expiresAt = Carbon::parse('2026-12-01 00:00:00');

        $this->contactPoint->markVerified('code', $expiresAt);

        $fresh = $this->contactPoint->fresh();

        expect($fresh->is_verified)->toBeTrue()
            ->and($fresh->verification_method)->toBe('code')
            ->and($fresh->verified_at->toDateTimeString())->toBe('2026-08-08 12:00:00')
            ->and($fresh->verification_expires_at->toDateTimeString())->toBe('2026-12-01 00:00:00')
            ->and($fresh->isCurrentlyVerified())->toBeTrue();
    });

    it('writes exactly one verified row and dispatches exactly one event', function (): void {
        Event::fake([ContactPointVerified::class]);

        $this->contactPoint->markVerified('code');

        Event::assertDispatchedTimes(ContactPointVerified::class, 1);
        expect(verificationHistoryCount($this->contactPoint))->toBe(1);

        $row = verificationHistoryRows($this->contactPoint)->first();

        expect($row->status)->toBe(VerificationEvent::STATUS_VERIFIED)
            ->and($row->method)->toBe('code')
            ->and($row->initiated_at)->not->toBeNull()
            ->and($row->completed_at)->not->toBeNull();
    });

    it('completes an open pending row instead of adding a second row', function (): void {
        Carbon::setTestNow('2026-08-08 12:00:00');
        $pending = $this->contactPoint->startVerification('code', ['channel' => 'sms']);

        Carbon::setTestNow('2026-08-08 12:05:00');
        Event::fake([ContactPointVerified::class]);

        $this->contactPoint->markVerified('code', Carbon::parse('2026-09-08 12:05:00'));

        Event::assertDispatchedTimes(ContactPointVerified::class, 1);
        expect(verificationHistoryCount($this->contactPoint))->toBe(1);

        $pending->refresh();

        expect($pending->status)->toBe(VerificationEvent::STATUS_VERIFIED)
            ->and($pending->initiated_at->toDateTimeString())->toBe('2026-08-08 12:00:00')
            ->and($pending->completed_at->toDateTimeString())->toBe('2026-08-08 12:05:00')
            ->and($pending->expires_at->toDateTimeString())->toBe('2026-09-08 12:05:00')
            ->and($pending->evidence)->toBe(['channel' => 'sms']);
    });

    it('completes only the most recent pending row', function (): void {
        Carbon::setTestNow('2026-08-08 12:00:00');
        $older = $this->contactPoint->startVerification('link');

        Carbon::setTestNow('2026-08-08 12:01:00');
        $newer = $this->contactPoint->startVerification('code');

        Carbon::setTestNow('2026-08-08 12:02:00');
        $this->contactPoint->markVerified('code');

        expect(verificationHistoryCount($this->contactPoint))->toBe(2)
            ->and($older->refresh()->status)->toBe(VerificationEvent::STATUS_PENDING)
            ->and($newer->refresh()->status)->toBe(VerificationEvent::STATUS_VERIFIED);
    });

    it('adopts a matching pending row and leaves the older same-method one open', function (): void {
        Carbon::setTestNow('2026-08-08 12:00:00');
        $older = $this->contactPoint->startVerification('code');

        Carbon::setTestNow('2026-08-08 12:01:00');
        $newer = $this->contactPoint->startVerification('code');

        Carbon::setTestNow('2026-08-08 12:02:00');
        $this->contactPoint->markVerified('code');

        expect(verificationHistoryCount($this->contactPoint))->toBe(2)
            ->and($older->refresh()->status)->toBe(VerificationEvent::STATUS_PENDING)
            ->and($newer->refresh()->status)->toBe(VerificationEvent::STATUS_VERIFIED)
            ->and($newer->method)->toBe('code');
    });

    it('does not adopt a pending row started under a different method', function (): void {
        Carbon::setTestNow('2026-08-08 12:00:00');
        $pending = $this->contactPoint->startVerification('link');

        Carbon::setTestNow('2026-08-08 12:05:00');
        $this->contactPoint->markVerified('manual');

        // The link attempt is still outstanding; the manual override gets its own row.
        expect(verificationHistoryCount($this->contactPoint))->toBe(2)
            ->and($pending->refresh()->status)->toBe(VerificationEvent::STATUS_PENDING)
            ->and($pending->method)->toBe('link')
            ->and($pending->completed_at)->toBeNull();

        $completed = verificationHistoryRows($this->contactPoint)->last();

        // The row must agree with both the model and the dispatched event.
        expect($completed->status)->toBe(VerificationEvent::STATUS_VERIFIED)
            ->and($completed->method)->toBe('manual')
            ->and($completed->completed_at->toDateTimeString())->toBe('2026-08-08 12:05:00')
            ->and($this->contactPoint->fresh()->verification_method)->toBe('manual');
    });

    it('does not adopt a mismatched pending row on the implicit path either', function (): void {
        $pending = $this->contactPoint->startVerification('link');

        $this->contactPoint->update(['is_verified' => true, 'verification_method' => 'imported']);

        expect(verificationHistoryCount($this->contactPoint))->toBe(2)
            ->and($pending->refresh()->status)->toBe(VerificationEvent::STATUS_PENDING)
            ->and(verificationHistoryRows($this->contactPoint)->last()->method)->toBe('imported');
    });

    it('records a fresh row and event each time an already-verified point is re-verified', function (): void {
        Event::fake([ContactPointVerified::class]);

        $this->contactPoint->markVerified('code');
        $this->contactPoint->markVerified('manual');

        Event::assertDispatchedTimes(ContactPointVerified::class, 2);
        expect(verificationHistoryCount($this->contactPoint))->toBe(2)
            ->and(verificationHistoryRows($this->contactPoint)->pluck('method')->all())->toBe(['code', 'manual']);
    });

    it('still dispatches the event but writes nothing when history logging is disabled', function (): void {
        config()->set('heyyou.verification.log_history', false);
        Event::fake([ContactPointVerified::class]);

        $this->contactPoint->markVerified('code');

        Event::assertDispatchedTimes(ContactPointVerified::class, 1);
        expect(verificationHistoryCount($this->contactPoint))->toBe(0)
            ->and($this->contactPoint->fresh()->is_verified)->toBeTrue();
    });
});

describe('default_expiration_days', function (): void {
    it('leaves verification unexpiring when the setting is null', function (): void {
        config(['heyyou.verification.default_expiration_days' => null]);

        $this->contactPoint->markVerified('code');

        expect($this->contactPoint->fresh()->verification_expires_at)->toBeNull()
            ->and(verificationHistoryRows($this->contactPoint)->first()->expires_at)->toBeNull();
    });

    it('applies the configured days when no explicit expiry is given', function (): void {
        Carbon::setTestNow('2026-08-08 12:00:00');
        config()->set('heyyou.verification.default_expiration_days', 30);

        $this->contactPoint->markVerified('code');

        expect($this->contactPoint->fresh()->verification_expires_at->toDateTimeString())->toBe('2026-09-07 12:00:00')
            ->and(verificationHistoryRows($this->contactPoint)->first()->expires_at->toDateTimeString())->toBe('2026-09-07 12:00:00');
    });

    it('yields to an explicit expiry', function (): void {
        Carbon::setTestNow('2026-08-08 12:00:00');
        config()->set('heyyou.verification.default_expiration_days', 30);

        $this->contactPoint->markVerified('code', Carbon::parse('2026-08-09 12:00:00'));

        expect($this->contactPoint->fresh()->verification_expires_at->toDateTimeString())->toBe('2026-08-09 12:00:00');
    });

    it('treats a non-positive setting as no expiry', function (): void {
        config()->set('heyyou.verification.default_expiration_days', 0);

        $this->contactPoint->markVerified('code');

        expect($this->contactPoint->fresh()->verification_expires_at)->toBeNull();
    });

    it('does not apply to the implicit path, which carries its own expiry', function (): void {
        config()->set('heyyou.verification.default_expiration_days', 30);

        $this->contactPoint->is_verified = true;
        $this->contactPoint->verification_method = 'imported';
        $this->contactPoint->save();

        expect($this->contactPoint->fresh()->verification_expires_at)->toBeNull();
    });
});

describe('markVerificationFailed', function (): void {
    it('dispatches ContactPointVerificationFailed and logs exactly one failed row', function (): void {
        Carbon::setTestNow('2026-08-08 12:00:00');
        Event::fake([ContactPointVerificationFailed::class]);

        $this->contactPoint->markVerificationFailed('code', 'Invalid verification code', ['attempt' => 3]);

        Event::assertDispatchedTimes(ContactPointVerificationFailed::class, 1);
        Event::assertDispatched(
            ContactPointVerificationFailed::class,
            fn (ContactPointVerificationFailed $event): bool => $event->contactPoint->id === $this->contactPoint->id
                && $event->method === 'code'
                && $event->reason === 'Invalid verification code',
        );

        expect(verificationHistoryCount($this->contactPoint))->toBe(1);

        $row = verificationHistoryRows($this->contactPoint)->first();

        expect($row->status)->toBe(VerificationEvent::STATUS_FAILED)
            ->and($row->method)->toBe('code')
            ->and($row->evidence)->toBe(['reason' => 'Invalid verification code', 'attempt' => 3])
            ->and($row->initiated_at->toDateTimeString())->toBe('2026-08-08 12:00:00')
            ->and($row->completed_at->toDateTimeString())->toBe('2026-08-08 12:00:00')
            ->and($row->expires_at)->toBeNull();
    });

    it('does not change is_verified', function (): void {
        $this->contactPoint->markVerified('code');

        $this->contactPoint->markVerificationFailed('code', 'Wrong code');

        expect($this->contactPoint->fresh()->is_verified)->toBeTrue();
    });

    it('leaves an open pending row open', function (): void {
        $pending = $this->contactPoint->startVerification('code');

        $this->contactPoint->markVerificationFailed('code', 'Wrong code');

        expect($pending->refresh()->status)->toBe(VerificationEvent::STATUS_PENDING)
            ->and(verificationHistoryCount($this->contactPoint))->toBe(2);
    });

    it('still dispatches the event but writes nothing when history logging is disabled', function (): void {
        config()->set('heyyou.verification.log_history', false);
        Event::fake([ContactPointVerificationFailed::class]);

        $this->contactPoint->markVerificationFailed('code', 'Wrong code');

        Event::assertDispatchedTimes(ContactPointVerificationFailed::class, 1);
        expect(verificationHistoryCount($this->contactPoint))->toBe(0);
    });
});

describe('markVerificationExpired', function (): void {
    it('clears is_verified, dispatches ContactPointVerificationExpired, and logs one expired row', function (): void {
        Carbon::setTestNow('2026-08-08 12:00:00');
        $this->contactPoint->markVerified('code', Carbon::parse('2026-08-09 12:00:00'));

        Carbon::setTestNow('2026-08-10 12:00:00');
        Event::fake([ContactPointVerificationExpired::class]);

        $this->contactPoint->markVerificationExpired();

        Event::assertDispatchedTimes(ContactPointVerificationExpired::class, 1);
        Event::assertDispatched(
            ContactPointVerificationExpired::class,
            fn (ContactPointVerificationExpired $event): bool => $event->contactPoint->id === $this->contactPoint->id,
        );

        expect($this->contactPoint->fresh()->is_verified)->toBeFalse()
            ->and($this->contactPoint->fresh()->isCurrentlyVerified())->toBeFalse()
            ->and(verificationHistoryCount($this->contactPoint))->toBe(2);

        $row = verificationHistoryRows($this->contactPoint)->last();

        expect($row->status)->toBe(VerificationEvent::STATUS_EXPIRED)
            ->and($row->method)->toBe('code')
            ->and($row->initiated_at->toDateTimeString())->toBe('2026-08-10 12:00:00')
            ->and($row->completed_at->toDateTimeString())->toBe('2026-08-10 12:00:00')
            ->and($row->expires_at->toDateTimeString())->toBe('2026-08-09 12:00:00');
    });

    it('falls back to an unknown method when none was recorded', function (): void {
        $this->contactPoint->is_verified = true;
        $this->contactPoint->save();

        $this->contactPoint->markVerificationExpired();

        expect(verificationHistoryRows($this->contactPoint)->last()->method)->toBe('unknown');
    });

    it('clears verified_at and the expiry alongside the flag', function (): void {
        Carbon::setTestNow('2026-01-01 12:00:00');
        $this->contactPoint->markVerified('code', Carbon::parse('2026-02-01 12:00:00'));

        Carbon::setTestNow('2026-03-01 12:00:00');
        $this->contactPoint->markVerificationExpired();

        $fresh = $this->contactPoint->fresh();

        expect($fresh->is_verified)->toBeFalse()
            ->and($fresh->verified_at)->toBeNull()
            ->and($fresh->verification_expires_at)->toBeNull();

        // The audit trail keeps what the live model gave up.
        $row = verificationHistoryRows($this->contactPoint)->last();

        expect($row->status)->toBe(VerificationEvent::STATUS_EXPIRED)
            ->and($row->expires_at->toDateTimeString())->toBe('2026-02-01 12:00:00');
    });

    it('leaves no stale timestamps for a later implicit re-verify to publish', function (): void {
        Carbon::setTestNow('2026-01-01 12:00:00');
        $this->contactPoint->markVerified('code', Carbon::parse('2026-02-01 12:00:00'));

        Carbon::setTestNow('2026-03-01 12:00:00');
        $this->contactPoint->markVerificationExpired();

        // Months later the host re-verifies through the implicit path.
        Carbon::setTestNow('2026-08-08 12:00:00');
        Event::fake([ContactPointVerified::class]);

        $this->contactPoint->update(['is_verified' => true]);

        Event::assertDispatched(
            ContactPointVerified::class,
            fn (ContactPointVerified $event): bool => $event->verifiedAt->toDateTimeString() === '2026-08-08 12:00:00',
        );

        $row = verificationHistoryRows($this->contactPoint)->last();

        expect($row->status)->toBe(VerificationEvent::STATUS_VERIFIED)
            ->and($row->completed_at->toDateTimeString())->toBe('2026-08-08 12:00:00')
            ->and($row->expires_at)->toBeNull();

        // Not left in the is_verified = true / isCurrentlyVerified() = false
        // contradiction the stale expiry used to produce.
        $fresh = $this->contactPoint->fresh();

        expect($fresh->is_verified)->toBeTrue()
            ->and($fresh->isCurrentlyVerified())->toBeTrue()
            ->and($fresh->verification_expires_at)->toBeNull();
    });

    it('is a no-op on a contact point that was never verified', function (): void {
        Event::fake([ContactPointVerificationExpired::class]);

        $this->contactPoint->markVerificationExpired();

        Event::assertNotDispatched(ContactPointVerificationExpired::class);
        expect(verificationHistoryCount($this->contactPoint))->toBe(0)
            ->and($this->contactPoint->fresh()->is_verified)->toBeFalse();
    });

    it('is a no-op when the verification has already been expired', function (): void {
        Carbon::setTestNow('2026-01-01 12:00:00');
        $this->contactPoint->markVerified('code');

        Carbon::setTestNow('2026-03-01 12:00:00');
        $this->contactPoint->markVerificationExpired();

        Event::fake([ContactPointVerificationExpired::class]);

        $this->contactPoint->markVerificationExpired();

        Event::assertNotDispatched(ContactPointVerificationExpired::class);
        expect(verificationHistoryCount($this->contactPoint))->toBe(2);
    });

    it('still dispatches the event but writes nothing when history logging is disabled', function (): void {
        // Expiry is a no-op on a point holding no verification, so the toggle needs
        // a real transition to exercise. Disable logging first so markVerified()
        // writes no row of its own.
        config()->set('heyyou.verification.log_history', false);
        $this->contactPoint->markVerified('code');

        Event::fake([ContactPointVerificationExpired::class]);

        $this->contactPoint->markVerificationExpired();

        Event::assertDispatchedTimes(ContactPointVerificationExpired::class, 1);
        expect(verificationHistoryCount($this->contactPoint))->toBe(0)
            ->and($this->contactPoint->fresh()->is_verified)->toBeFalse();
    });
});

describe('the implicit path (assign is_verified, save)', function (): void {
    it('still dispatches ContactPointVerified exactly once and now logs exactly one row', function (): void {
        Carbon::setTestNow('2026-08-08 12:00:00');
        Event::fake([ContactPointVerified::class]);

        $this->contactPoint->is_verified = true;
        $this->contactPoint->verified_at = Carbon::now();
        $this->contactPoint->verification_method = 'imported';
        $this->contactPoint->save();

        Event::assertDispatchedTimes(ContactPointVerified::class, 1);
        Event::assertDispatched(
            ContactPointVerified::class,
            fn (ContactPointVerified $event): bool => $event->method === 'imported',
        );

        expect(verificationHistoryCount($this->contactPoint))->toBe(1);

        $row = verificationHistoryRows($this->contactPoint)->first();

        expect($row->status)->toBe(VerificationEvent::STATUS_VERIFIED)
            ->and($row->method)->toBe('imported')
            ->and($row->completed_at->toDateTimeString())->toBe('2026-08-08 12:00:00');
    });

    it('records an unknown method when none was set', function (): void {
        $this->contactPoint->update(['is_verified' => true]);

        expect(verificationHistoryRows($this->contactPoint)->first()->method)->toBe('unknown');
    });

    it('completes an open pending row instead of adding a second row', function (): void {
        $pending = $this->contactPoint->startVerification('link');

        $this->contactPoint->update(['is_verified' => true, 'verification_method' => 'link']);

        expect(verificationHistoryCount($this->contactPoint))->toBe(1)
            ->and($pending->refresh()->status)->toBe(VerificationEvent::STATUS_VERIFIED);
    });

    it('still dispatches the event but writes nothing when history logging is disabled', function (): void {
        config()->set('heyyou.verification.log_history', false);
        Event::fake([ContactPointVerified::class]);

        $this->contactPoint->update(['is_verified' => true]);

        Event::assertDispatchedTimes(ContactPointVerified::class, 1);
        expect(verificationHistoryCount($this->contactPoint))->toBe(0);
    });

    it('does not re-record when a later save leaves is_verified untouched', function (): void {
        Event::fake([ContactPointVerified::class]);

        $this->contactPoint->update(['is_verified' => true, 'verification_method' => 'code']);
        $this->contactPoint->update(['label' => 'Work']);

        Event::assertDispatchedTimes(ContactPointVerified::class, 1);
        expect(verificationHistoryCount($this->contactPoint))->toBe(1);
    });
});

describe('the invariant: events and history rows move together', function (): void {
    it('never logs a verified row without dispatching ContactPointVerified', function (): void {
        Event::fake([ContactPointVerified::class]);

        // Every path that can produce a verified row.
        $explicit = ContactPoint::create([
            'party_id' => $this->party->id,
            'channel' => 'email',
            'value_raw' => 'explicit@example.com',
        ]);
        $explicit->markVerified('code');

        $implicit = ContactPoint::create([
            'party_id' => $this->party->id,
            'channel' => 'email',
            'value_raw' => 'implicit@example.com',
        ]);
        $implicit->update(['is_verified' => true]);

        $completed = ContactPoint::create([
            'party_id' => $this->party->id,
            'channel' => 'email',
            'value_raw' => 'completed@example.com',
        ]);
        $completed->startVerification('link');
        $completed->markVerified('link');

        $verifiedRows = VerificationEvent::query()
            ->where('status', VerificationEvent::STATUS_VERIFIED)
            ->count();

        expect($verifiedRows)->toBe(3);
        Event::assertDispatchedTimes(ContactPointVerified::class, 3);
    });

    it('neither dispatches nor logs when a contact point is created already verified', function (): void {
        Event::fake([ContactPointVerified::class]);

        $contactPoint = ContactPoint::create([
            'party_id' => $this->party->id,
            'channel' => 'email',
            'value_raw' => 'preverified@example.com',
            'is_verified' => true,
            'verified_at' => Carbon::now(),
            'verification_method' => 'imported',
        ]);

        // Both halves absent together: creation is not a verification transition.
        Event::assertNotDispatched(ContactPointVerified::class);
        expect(verificationHistoryCount($contactPoint))->toBe(0);
    });

    it('writes no history at all when logging is disabled, on every path', function (): void {
        config()->set('heyyou.verification.log_history', false);
        Event::fake([
            ContactPointVerified::class,
            ContactPointVerificationFailed::class,
            ContactPointVerificationExpired::class,
        ]);

        $this->contactPoint->startVerification('code');
        $this->contactPoint->markVerificationFailed('code', 'Wrong code');
        $this->contactPoint->markVerified('code');
        $this->contactPoint->markVerificationExpired();

        expect(VerificationEvent::query()->count())->toBe(0);

        // Signalling is unaffected by the history switch.
        Event::assertDispatchedTimes(ContactPointVerified::class, 1);
        Event::assertDispatchedTimes(ContactPointVerificationFailed::class, 1);
        Event::assertDispatchedTimes(ContactPointVerificationExpired::class, 1);
    });

    it('writes one row per outcome event when logging is enabled, on every path', function (): void {
        Event::fake([
            ContactPointVerified::class,
            ContactPointVerificationFailed::class,
            ContactPointVerificationExpired::class,
        ]);

        Carbon::setTestNow('2026-08-08 12:00:00');
        $this->contactPoint->markVerificationFailed('code', 'Wrong code');

        Carbon::setTestNow('2026-08-08 12:01:00');
        $this->contactPoint->markVerified('code');

        Carbon::setTestNow('2026-08-08 12:02:00');
        $this->contactPoint->markVerificationExpired();

        Event::assertDispatchedTimes(ContactPointVerified::class, 1);
        Event::assertDispatchedTimes(ContactPointVerificationFailed::class, 1);
        Event::assertDispatchedTimes(ContactPointVerificationExpired::class, 1);

        expect(verificationHistoryRows($this->contactPoint)->pluck('status')->all())->toBe([
            VerificationEvent::STATUS_FAILED,
            VerificationEvent::STATUS_VERIFIED,
            VerificationEvent::STATUS_EXPIRED,
        ]);
    });
});
