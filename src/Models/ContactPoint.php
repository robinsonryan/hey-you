<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use RobinsonRyan\HeyYou\Contracts\EventDispatcher;
use RobinsonRyan\HeyYou\Contracts\Registries\NormalizerRegistry;
use RobinsonRyan\HeyYou\Database\Factories\ContactPointFactory;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointBounced;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointCreated;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointDeleted;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointMarkedUnreachable;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointRestored;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointUpdated;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointVerificationExpired;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointVerificationFailed;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointVerified;
use RobinsonRyan\HeyYou\Support\TablePrefixer;
use RobinsonRyan\HeyYou\Traits\ConfiguresIdentifiers;

/**
 * @property string $id
 * @property string $party_id
 * @property string $channel
 * @property string $value_raw
 * @property string $value_normalized
 * @property string|null $label
 * @property string $status
 * @property bool $is_primary
 * @property bool $is_verified
 * @property Carbon|null $verified_at
 * @property string|null $verification_method
 * @property Carbon|null $verification_expires_at
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Party $party
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ContactPointPurpose> $purposes
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ContactPointConsent> $consents
 * @property-read \Illuminate\Database\Eloquent\Collection<int, VerificationEvent> $verificationEvents
 */
final class ContactPoint extends Model
{
    use ConfiguresIdentifiers;

    /** @use HasFactory<ContactPointFactory> */
    use HasFactory;

    use SoftDeletes;

    protected static function newFactory(): ContactPointFactory
    {
        return ContactPointFactory::new();
    }

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_UNREACHABLE = 'unreachable';

    public const STATUS_BOUNCED = 'bounced';

    public const STATUS_BLOCKED = 'blocked';

    /**
     * Guard for the verified path.
     *
     * Set while markVerified() is persisting, so the `updated` hook — which
     * records the *implicit* path (assign `is_verified`, save) — does not record
     * the same verification a second time.
     */
    private bool $recordingVerification = false;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
        'is_primary' => false,
        'is_verified' => false,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'party_id',
        'channel',
        'value_raw',
        'value_normalized',
        'label',
        'status',
        'is_primary',
        'is_verified',
        'verified_at',
        'verification_method',
        'verification_expires_at',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
            'verification_expires_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function getTable(): string
    {
        return TablePrefixer::prefix('contact_points');
    }

    protected static function booted(): void
    {
        self::saving(function (ContactPoint $contactPoint): void {
            // getAttribute() rather than the property: on an unsaved model the
            // (non-nullable) normalized value has not been derived yet.
            if ($contactPoint->isDirty('value_raw') || $contactPoint->getAttribute('value_normalized') === null) {
                $normalizer = app(NormalizerRegistry::class)->for($contactPoint->channel);
                $contactPoint->value_normalized = $normalizer->normalize($contactPoint->value_raw);
            }
        });

        self::created(function (ContactPoint $contactPoint): void {
            app(EventDispatcher::class)->dispatch(new ContactPointCreated(
                $contactPoint,
                $contactPoint->party,
            ));
        });

        self::updated(function (ContactPoint $contactPoint): void {
            // The implicit path: something assigned is_verified = true and saved.
            // markVerified() sets the guard because it records the outcome itself.
            if ($contactPoint->wasChanged('is_verified') && $contactPoint->is_verified && ! $contactPoint->recordingVerification) {
                $contactPoint->recordVerified(
                    $contactPoint->verification_method ?? 'unknown',
                    $contactPoint->verified_at ?? Carbon::now(),
                    $contactPoint->verification_expires_at,
                );
            }

            // Check if status changed to bounced
            if ($contactPoint->wasChanged('status') && $contactPoint->status === self::STATUS_BOUNCED) {
                app(EventDispatcher::class)->dispatch(new ContactPointBounced(
                    $contactPoint,
                    ['status' => $contactPoint->status],
                ));
            }

            // Check if status changed to unreachable
            if ($contactPoint->wasChanged('status') && $contactPoint->status === self::STATUS_UNREACHABLE) {
                app(EventDispatcher::class)->dispatch(new ContactPointMarkedUnreachable(
                    $contactPoint,
                    'Status changed to unreachable',
                ));
            }

            app(EventDispatcher::class)->dispatch(new ContactPointUpdated(
                $contactPoint,
                $contactPoint->party,
                $contactPoint->getChanges(),
            ));
        });

        self::deleted(function (ContactPoint $contactPoint): void {
            app(EventDispatcher::class)->dispatch(new ContactPointDeleted(
                $contactPoint,
                $contactPoint->party,
            ));
        });

        self::restored(function (ContactPoint $contactPoint): void {
            app(EventDispatcher::class)->dispatch(new ContactPointRestored(
                $contactPoint,
                $contactPoint->party,
            ));
        });
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * @return HasMany<ContactPointPurpose, $this>
     */
    public function purposes(): HasMany
    {
        return $this->hasMany(ContactPointPurpose::class);
    }

    /**
     * @return HasMany<ContactPointConsent, $this>
     */
    public function consents(): HasMany
    {
        return $this->hasMany(ContactPointConsent::class);
    }

    /**
     * @return HasMany<VerificationEvent, $this>
     */
    public function verificationEvents(): HasMany
    {
        return $this->hasMany(VerificationEvent::class);
    }

    /**
     * Check if the contact point is currently verified.
     */
    public function isCurrentlyVerified(): bool
    {
        if (! $this->is_verified) {
            return false;
        }

        if ($this->verification_expires_at !== null && $this->verification_expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Record the start of a verification attempt.
     *
     * Returns the pending history row, or null when history logging is disabled.
     * There is deliberately no event here: the package publishes verification
     * *outcomes* (verified / failed / expired), so `pending` is the one status
     * with no matching event. Every status that has an event dispatches it.
     *
     * The package performs no verification itself (spec §1.2 — not a messaging
     * provider); the host application sends the code or link and reports back.
     *
     * @param  array<string, mixed>  $evidence
     */
    public function startVerification(string $method, array $evidence = []): ?VerificationEvent
    {
        if (! $this->shouldLogVerificationHistory()) {
            return null;
        }

        return $this->verificationEvents()->create([
            'status' => VerificationEvent::STATUS_PENDING,
            'method' => $method,
            'evidence' => $evidence === [] ? null : $evidence,
            'initiated_at' => Carbon::now(),
            'completed_at' => null,
        ]);
    }

    /**
     * Mark this contact point verified, dispatch ContactPointVerified, and record
     * the outcome in history.
     *
     * With no explicit expiry, `heyyou.verification.default_expiration_days`
     * applies. Its shipped value is null, which means "never expires" — the
     * behaviour every existing consumer already has.
     */
    public function markVerified(string $method, ?Carbon $expiresAt = null): void
    {
        $verifiedAt = Carbon::now();
        $expiresAt ??= $this->defaultVerificationExpiry($verifiedAt);

        $this->is_verified = true;
        $this->verified_at = $verifiedAt;
        $this->verification_method = $method;
        $this->verification_expires_at = $expiresAt;

        // Suppress the `updated` hook's own recording so one verification never
        // produces two events or two history rows. recordVerified() below is the
        // single place the verified outcome is published, for both paths.
        $this->recordingVerification = true;

        try {
            $this->save();
        } finally {
            $this->recordingVerification = false;
        }

        $this->recordVerified($method, $verifiedAt, $expiresAt);
    }

    /**
     * Record a failed verification attempt.
     *
     * Does not touch `is_verified`: a failed attempt never revokes a verification
     * the contact point already holds. An open pending row is left open too — a
     * rejected code ends the attempt's try, not the attempt.
     *
     * @param  array<string, mixed>  $evidence
     */
    public function markVerificationFailed(string $method, string $reason, array $evidence = []): void
    {
        app(EventDispatcher::class)->dispatch(new ContactPointVerificationFailed(
            $this,
            $method,
            $reason,
        ));

        if (! $this->shouldLogVerificationHistory()) {
            return;
        }

        $failedAt = Carbon::now();

        $this->verificationEvents()->create([
            'status' => VerificationEvent::STATUS_FAILED,
            'method' => $method,
            // The schema has no reason column, so the reason rides in evidence.
            // A caller-supplied `reason` key wins.
            'evidence' => array_merge(['reason' => $reason], $evidence),
            'initiated_at' => $failedAt,
            'completed_at' => $failedAt,
        ]);
    }

    /**
     * Expire this contact point's verification.
     *
     * Called by the host application when it decides a verification has aged out;
     * the package ships no scheduler for this (spec §1.2).
     */
    public function markVerificationExpired(): void
    {
        $method = $this->verification_method ?? 'unknown';
        $expiredAt = $this->verification_expires_at;

        $this->is_verified = false;
        $this->save();

        app(EventDispatcher::class)->dispatch(new ContactPointVerificationExpired($this));

        if (! $this->shouldLogVerificationHistory()) {
            return;
        }

        $recordedAt = Carbon::now();

        $this->verificationEvents()->create([
            'status' => VerificationEvent::STATUS_EXPIRED,
            'method' => $method,
            'initiated_at' => $recordedAt,
            'completed_at' => $recordedAt,
            'expires_at' => $expiredAt,
        ]);
    }

    /**
     * Publish a successful verification: the event first, then history.
     *
     * The single call site for both, reached from markVerified() and from the
     * `updated` hook's implicit path. Keeping them in one method is what makes it
     * impossible to add a verified-path history write with no event, or an event
     * with no history write.
     */
    private function recordVerified(string $method, Carbon $verifiedAt, ?Carbon $expiresAt): void
    {
        app(EventDispatcher::class)->dispatch(new ContactPointVerified(
            $this,
            $method,
            $verifiedAt,
        ));

        if (! $this->shouldLogVerificationHistory()) {
            return;
        }

        $pending = $this->verificationEvents()
            ->where('status', VerificationEvent::STATUS_PENDING)
            ->orderByDesc('initiated_at')
            ->orderByDesc('id')
            ->first();

        if ($pending instanceof VerificationEvent) {
            $pending->update([
                'status' => VerificationEvent::STATUS_VERIFIED,
                'completed_at' => $verifiedAt,
                'expires_at' => $expiresAt,
            ]);

            return;
        }

        $this->verificationEvents()->create([
            'status' => VerificationEvent::STATUS_VERIFIED,
            'method' => $method,
            'initiated_at' => $verifiedAt,
            'completed_at' => $verifiedAt,
            'expires_at' => $expiresAt,
        ]);
    }

    private function shouldLogVerificationHistory(): bool
    {
        return (bool) config('heyyou.verification.log_history', true);
    }

    /**
     * The configured default expiry, or null when the package should not expire
     * verifications at all (the shipped default).
     */
    private function defaultVerificationExpiry(Carbon $verifiedAt): ?Carbon
    {
        $days = config('heyyou.verification.default_expiration_days');

        if (! is_numeric($days)) {
            return null;
        }

        $days = (int) $days;

        return $days > 0 ? $verifiedAt->copy()->addDays($days) : null;
    }
}
