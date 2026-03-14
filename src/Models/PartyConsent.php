<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use RobinsonRyan\HeyYou\Contracts\EventDispatcher;
use RobinsonRyan\HeyYou\Events\Consent\ConsentGranted;
use RobinsonRyan\HeyYou\Events\Consent\ConsentRevoked;
use RobinsonRyan\HeyYou\Support\TablePrefixer;
use RobinsonRyan\HeyYou\Traits\ConfiguresIdentifiers;

/**
 * @property string $id
 * @property string $party_id
 * @property string|null $channel
 * @property string $purpose_category
 * @property string $status
 * @property Carbon $captured_at
 * @property string $source
 * @property array<string, mixed>|null $evidence
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Party $party
 */
final class PartyConsent extends Model
{
    use ConfiguresIdentifiers;
    use SoftDeletes;

    public const STATUS_OPTED_IN = 'opted_in';

    public const STATUS_OPTED_OUT = 'opted_out';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'party_id',
        'channel',
        'purpose_category',
        'status',
        'captured_at',
        'source',
        'evidence',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'evidence' => 'array',
        ];
    }

    public function getTable(): string
    {
        return TablePrefixer::prefix('party_consents');
    }

    protected static function booted(): void
    {
        self::created(function (PartyConsent $consent) {
            $event = $consent->status === self::STATUS_OPTED_IN
                ? new ConsentGranted($consent, 'party', $consent->purpose_category, $consent->channel)
                : new ConsentRevoked($consent, 'party', $consent->purpose_category, $consent->channel);

            app(EventDispatcher::class)->dispatch($event);
        });

        self::updated(function (PartyConsent $consent) {
            if ($consent->wasChanged('status')) {
                $event = $consent->status === self::STATUS_OPTED_IN
                    ? new ConsentGranted($consent, 'party', $consent->purpose_category, $consent->channel)
                    : new ConsentRevoked($consent, 'party', $consent->purpose_category, $consent->channel);

                app(EventDispatcher::class)->dispatch($event);
            }
        });
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
}
