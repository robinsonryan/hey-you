<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use RobinsonRyan\HeyYou\Contracts\EventDispatcher;
use RobinsonRyan\HeyYou\Database\Factories\PartyFactory;
use RobinsonRyan\HeyYou\Events\Party\PartyCreated;
use RobinsonRyan\HeyYou\Events\Party\PartyDeleted;
use RobinsonRyan\HeyYou\Events\Party\PartyUpdated;
use RobinsonRyan\HeyYou\Support\TablePrefixer;

/**
 * @property int $id
 * @property string $partyable_type
 * @property int|string $partyable_id
 * @property string $display_name_cached
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Model $partyable
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PartyRelationship> $outgoingRelationships
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PartyRelationship> $incomingRelationships
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ContactPoint> $contactPoints
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Address> $addresses
 * @property-read \Illuminate\Database\Eloquent\Collection<int, RoleAssignment> $roleAssignments
 * @property-read \Illuminate\Database\Eloquent\Collection<int, RoleAssignment> $scopedRoleAssignments
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PartyConsent> $consents
 * @property-read \Illuminate\Database\Eloquent\Collection<int, DoNotContact> $dncRules
 */
final class Party extends Model
{
    /** @use HasFactory<PartyFactory> */
    use HasFactory;

    use SoftDeletes;

    protected static function newFactory(): PartyFactory
    {
        return PartyFactory::new();
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'partyable_type',
        'partyable_id',
        'display_name_cached',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function getTable(): string
    {
        return TablePrefixer::prefix('parties');
    }

    protected static function booted(): void
    {
        self::created(function (Party $party) {
            $partyable = self::safeLoadPartyable($party);
            if ($partyable !== null) {
                app(EventDispatcher::class)->dispatch(new PartyCreated($party, $partyable));
            }
        });

        self::updated(function (Party $party) {
            $partyable = self::safeLoadPartyable($party);
            if ($partyable !== null) {
                app(EventDispatcher::class)->dispatch(new PartyUpdated(
                    $party,
                    $partyable,
                    $party->getChanges(),
                ));
            }
        });

        self::deleted(function (Party $party) {
            $partyable = self::safeLoadPartyable($party);
            if ($partyable !== null) {
                app(EventDispatcher::class)->dispatch(new PartyDeleted($party, $partyable));
            }
        });
    }

    /**
     * Safely load the partyable relationship, returning null if the class doesn't exist.
     */
    private static function safeLoadPartyable(Party $party): ?Model
    {
        $morphClass = $party->partyable_type;

        if (! class_exists($morphClass)) {
            return null;
        }

        return $party->partyable;
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function partyable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<PartyRelationship, $this>
     */
    public function outgoingRelationships(): HasMany
    {
        return $this->hasMany(PartyRelationship::class, 'from_party_id');
    }

    /**
     * @return HasMany<PartyRelationship, $this>
     */
    public function incomingRelationships(): HasMany
    {
        return $this->hasMany(PartyRelationship::class, 'to_party_id');
    }

    /**
     * @return HasMany<ContactPoint, $this>
     */
    public function contactPoints(): HasMany
    {
        return $this->hasMany(ContactPoint::class);
    }

    /**
     * @return HasMany<Address, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /**
     * Role assignments where this party holds a role.
     *
     * @return HasMany<RoleAssignment, $this>
     */
    public function roleAssignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }

    /**
     * Role assignments scoped to this party (e.g., roles within this organization).
     *
     * @return HasMany<RoleAssignment, $this>
     */
    public function scopedRoleAssignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class, 'scope_party_id');
    }

    /**
     * @return HasMany<PartyConsent, $this>
     */
    public function consents(): HasMany
    {
        return $this->hasMany(PartyConsent::class);
    }

    /**
     * @return HasMany<DoNotContact, $this>
     */
    public function dncRules(): HasMany
    {
        return $this->hasMany(DoNotContact::class);
    }
}
