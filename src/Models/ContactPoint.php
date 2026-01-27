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
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointCreated;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointDeleted;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointUpdated;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointVerified;
use RobinsonRyan\HeyYou\Support\TablePrefixer;

/**
 * @property int $id
 * @property int $party_id
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
        self::saving(function (ContactPoint $contactPoint) {
            if ($contactPoint->isDirty('value_raw') || $contactPoint->value_normalized === null) {
                $normalizer = app(NormalizerRegistry::class)->for($contactPoint->channel);
                $contactPoint->value_normalized = $normalizer->normalize($contactPoint->value_raw);
            }
        });

        self::created(function (ContactPoint $contactPoint) {
            app(EventDispatcher::class)->dispatch(new ContactPointCreated(
                $contactPoint,
                $contactPoint->party,
            ));
        });

        self::updated(function (ContactPoint $contactPoint) {
            // Check if verification status changed to verified
            if ($contactPoint->wasChanged('is_verified') && $contactPoint->is_verified) {
                app(EventDispatcher::class)->dispatch(new ContactPointVerified(
                    $contactPoint,
                    $contactPoint->verification_method ?? 'unknown',
                    $contactPoint->verified_at ?? Carbon::now(),
                ));
            }

            app(EventDispatcher::class)->dispatch(new ContactPointUpdated(
                $contactPoint,
                $contactPoint->party,
                $contactPoint->getChanges(),
            ));
        });

        self::deleted(function (ContactPoint $contactPoint) {
            app(EventDispatcher::class)->dispatch(new ContactPointDeleted(
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
}
