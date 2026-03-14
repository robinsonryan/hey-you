<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use RobinsonRyan\HeyYou\Contracts\EventDispatcher;
use RobinsonRyan\HeyYou\Database\Factories\AddressFactory;
use RobinsonRyan\HeyYou\Events\Address\AddressCreated;
use RobinsonRyan\HeyYou\Events\Address\AddressDeleted;
use RobinsonRyan\HeyYou\Events\Address\AddressRestored;
use RobinsonRyan\HeyYou\Events\Address\AddressUpdated;
use RobinsonRyan\HeyYou\Events\Address\AddressValidated;
use RobinsonRyan\HeyYou\Events\Address\AddressValidationFailed;
use RobinsonRyan\HeyYou\Support\TablePrefixer;
use RobinsonRyan\HeyYou\Traits\ConfiguresIdentifiers;

/**
 * @property string $id
 * @property string $party_id
 * @property string $purpose
 * @property bool $is_primary
 * @property string|null $label
 * @property string $line1
 * @property string|null $line2
 * @property string $city
 * @property string|null $region
 * @property string|null $postal_code
 * @property string $country_code
 * @property array{lat: float, lng: float}|null $geocode
 * @property string|null $timezone
 * @property string $validation_status
 * @property string|null $formatted_cached
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_to
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Party $party
 */
final class Address extends Model
{
    use ConfiguresIdentifiers;

    /** @use HasFactory<AddressFactory> */
    use HasFactory;

    use SoftDeletes;

    protected static function newFactory(): AddressFactory
    {
        return AddressFactory::new();
    }

    protected static function booted(): void
    {
        self::created(function (Address $address) {
            app(EventDispatcher::class)->dispatch(new AddressCreated($address, $address->party));
        });

        self::updated(function (Address $address) {
            $changes = $address->getChanges();

            // Check for validation_status changes
            if ($address->wasChanged('validation_status')) {
                if ($address->validation_status === self::STATUS_VERIFIED) {
                    app(EventDispatcher::class)->dispatch(new AddressValidated(
                        $address,
                        ['status' => $address->validation_status, 'geocode' => $address->geocode],
                    ));
                } elseif ($address->validation_status === self::STATUS_INVALID) {
                    app(EventDispatcher::class)->dispatch(new AddressValidationFailed(
                        $address,
                        ['status' => $address->validation_status],
                    ));
                }
            }

            app(EventDispatcher::class)->dispatch(new AddressUpdated(
                $address,
                $address->party,
                $changes,
            ));
        });

        self::deleted(function (Address $address) {
            app(EventDispatcher::class)->dispatch(new AddressDeleted($address, $address->party));
        });

        self::restored(function (Address $address) {
            app(EventDispatcher::class)->dispatch(new AddressRestored($address, $address->party));
        });
    }

    public const STATUS_UNVERIFIED = 'unverified';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_INVALID = 'invalid';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_primary' => false,
        'validation_status' => self::STATUS_UNVERIFIED,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'party_id',
        'purpose',
        'is_primary',
        'label',
        'line1',
        'line2',
        'city',
        'region',
        'postal_code',
        'country_code',
        'geocode',
        'timezone',
        'validation_status',
        'formatted_cached',
        'valid_from',
        'valid_to',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'geocode' => 'array',
            'valid_from' => 'datetime',
            'valid_to' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function getTable(): string
    {
        return TablePrefixer::prefix('addresses');
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * Scope to only include currently valid addresses.
     *
     * @param  Builder<Address>  $query
     * @return Builder<Address>
     */
    public function scopeCurrent(Builder $query): Builder
    {
        $now = Carbon::now();

        return $query
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('valid_from')
                    ->orWhere('valid_from', '<=', $now);
            })
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('valid_to')
                    ->orWhere('valid_to', '>=', $now);
            });
    }
}
