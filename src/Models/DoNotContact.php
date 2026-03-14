<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use RobinsonRyan\HeyYou\Contracts\EventDispatcher;
use RobinsonRyan\HeyYou\Events\Dnc\DncRuleCreated;
use RobinsonRyan\HeyYou\Events\Dnc\DncRuleRemoved;
use RobinsonRyan\HeyYou\Support\TablePrefixer;
use RobinsonRyan\HeyYou\Traits\ConfiguresIdentifiers;

/**
 * @property string $id
 * @property string $party_id
 * @property string|null $contact_point_id
 * @property string|null $channel
 * @property string|null $purpose
 * @property string|null $reason
 * @property string $source
 * @property string|null $created_by_type
 * @property string|null $created_by_id
 * @property Carbon $effective_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Party $party
 * @property-read ContactPoint|null $contactPoint
 * @property-read Model|null $createdBy
 */
final class DoNotContact extends Model
{
    use ConfiguresIdentifiers;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'party_id',
        'contact_point_id',
        'channel',
        'purpose',
        'reason',
        'source',
        'created_by_type',
        'created_by_id',
        'effective_at',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return TablePrefixer::prefix('do_not_contacts');
    }

    protected static function booted(): void
    {
        self::created(function (DoNotContact $dnc) {
            app(EventDispatcher::class)->dispatch(new DncRuleCreated(
                $dnc,
                $dnc->party,
                $dnc->determineScope(),
            ));
        });

        self::deleted(function (DoNotContact $dnc) {
            app(EventDispatcher::class)->dispatch(new DncRuleRemoved(
                $dnc,
                $dnc->party,
                $dnc->determineScope(),
            ));
        });
    }

    /**
     * Determine the scope of this DNC rule.
     */
    public function determineScope(): string
    {
        if ($this->contact_point_id !== null) {
            return 'contact_point';
        }

        if ($this->channel !== null && $this->purpose !== null) {
            return 'channel_purpose';
        }

        if ($this->channel !== null) {
            return 'channel';
        }

        if ($this->purpose !== null) {
            return 'purpose';
        }

        return 'party';
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * @return BelongsTo<ContactPoint, $this>
     */
    public function contactPoint(): BelongsTo
    {
        return $this->belongsTo(ContactPoint::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function createdBy(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope to only include currently active DNC rules.
     *
     * @param  Builder<DoNotContact>  $query
     * @return Builder<DoNotContact>
     */
    public function scopeActive(Builder $query): Builder
    {
        $now = Carbon::now();

        return $query
            ->where('effective_at', '<=', $now)
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', $now);
            });
    }
}
