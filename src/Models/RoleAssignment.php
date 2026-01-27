<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use RobinsonRyan\HeyYou\Database\Factories\RoleAssignmentFactory;
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
