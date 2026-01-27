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
use RobinsonRyan\HeyYou\Database\Factories\RoleAssignmentFactory;
use RobinsonRyan\HeyYou\Events\RoleAssignment\RoleAssignmentCreated;
use RobinsonRyan\HeyYou\Events\RoleAssignment\RoleAssignmentDeleted;
use RobinsonRyan\HeyYou\Events\RoleAssignment\RoleAssignmentExpired;
use RobinsonRyan\HeyYou\Events\RoleAssignment\RoleAssignmentUpdated;
use RobinsonRyan\HeyYou\Support\TablePrefixer;

/**
 * @property int $id
 * @property int $party_id
 * @property int $scope_party_id
 * @property string $role
 * @property int $priority
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_to
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Party $party
 * @property-read Party $scopeParty
 */
final class RoleAssignment extends Model
{
    /** @use HasFactory<RoleAssignmentFactory> */
    use HasFactory;

    use SoftDeletes;

    protected static function newFactory(): RoleAssignmentFactory
    {
        return RoleAssignmentFactory::new();
    }

    protected static function booted(): void
    {
        self::created(function (RoleAssignment $roleAssignment) {
            app(EventDispatcher::class)->dispatch(new RoleAssignmentCreated(
                $roleAssignment,
                $roleAssignment->party,
                $roleAssignment->scopeParty,
            ));
        });

        self::updated(function (RoleAssignment $roleAssignment) {
            $changes = $roleAssignment->getChanges();

            app(EventDispatcher::class)->dispatch(new RoleAssignmentUpdated(
                $roleAssignment,
                $changes,
            ));

            // Dispatch RoleAssignmentExpired if valid_to was just set
            if (array_key_exists('valid_to', $changes) && $roleAssignment->valid_to !== null) {
                app(EventDispatcher::class)->dispatch(new RoleAssignmentExpired($roleAssignment));
            }
        });

        self::deleted(function (RoleAssignment $roleAssignment) {
            app(EventDispatcher::class)->dispatch(new RoleAssignmentDeleted($roleAssignment));
        });
    }

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'priority' => 0,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'party_id',
        'scope_party_id',
        'role',
        'priority',
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
            'priority' => 'integer',
            'valid_from' => 'datetime',
            'valid_to' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function getTable(): string
    {
        return TablePrefixer::prefix('role_assignments');
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function scopeParty(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'scope_party_id');
    }

    /**
     * Scope to only include currently valid role assignments.
     *
     * @param  Builder<RoleAssignment>  $query
     * @return Builder<RoleAssignment>
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
