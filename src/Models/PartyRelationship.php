<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use RobinsonRyan\HeyYou\Support\TablePrefixer;

/**
 * @property int $id
 * @property int $from_party_id
 * @property int $to_party_id
 * @property string $relationship_type
 * @property string|null $label
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_to
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Party $fromParty
 * @property-read Party $toParty
 */
final class PartyRelationship extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'from_party_id',
        'to_party_id',
        'relationship_type',
        'label',
        'metadata',
        'valid_from',
        'valid_to',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'valid_from' => 'datetime',
            'valid_to' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return TablePrefixer::prefix('party_relationships');
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function fromParty(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'from_party_id');
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function toParty(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'to_party_id');
    }

    /**
     * Scope to only include currently valid relationships.
     *
     * @param  Builder<PartyRelationship>  $query
     * @return Builder<PartyRelationship>
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
