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

/**
 * @property int $id
 * @property int $contact_point_id
 * @property string $purpose_category
 * @property string $status
 * @property Carbon $captured_at
 * @property string $source
 * @property array<string, mixed>|null $evidence
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read ContactPoint $contactPoint
 */
final class ContactPointConsent extends Model
{
    use SoftDeletes;

    public const STATUS_OPTED_IN = 'opted_in';

    public const STATUS_OPTED_OUT = 'opted_out';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'contact_point_id',
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
        return TablePrefixer::prefix('contact_point_consents');
    }

    protected static function booted(): void
    {
        self::created(function (ContactPointConsent $consent) {
            $event = $consent->status === self::STATUS_OPTED_IN
                ? new ConsentGranted($consent, 'contact_point', $consent->purpose_category, null)
                : new ConsentRevoked($consent, 'contact_point', $consent->purpose_category, null);

            app(EventDispatcher::class)->dispatch($event);
        });

        self::updated(function (ContactPointConsent $consent) {
            if ($consent->wasChanged('status')) {
                $event = $consent->status === self::STATUS_OPTED_IN
                    ? new ConsentGranted($consent, 'contact_point', $consent->purpose_category, null)
                    : new ConsentRevoked($consent, 'contact_point', $consent->purpose_category, null);

                app(EventDispatcher::class)->dispatch($event);
            }
        });
    }

    /**
     * @return BelongsTo<ContactPoint, $this>
     */
    public function contactPoint(): BelongsTo
    {
        return $this->belongsTo(ContactPoint::class);
    }
}
