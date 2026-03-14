<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use RobinsonRyan\HeyYou\Contracts\EventDispatcher;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointPurposeAttached;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointPurposeDetached;
use RobinsonRyan\HeyYou\Support\TablePrefixer;
use RobinsonRyan\HeyYou\Traits\ConfiguresIdentifiers;

/**
 * @property string $id
 * @property string $contact_point_id
 * @property string $purpose
 * @property int $priority
 * @property bool $is_preferred
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ContactPoint $contactPoint
 */
final class ContactPointPurpose extends Model
{
    use ConfiguresIdentifiers;
    protected static function booted(): void
    {
        self::created(function (ContactPointPurpose $purpose) {
            app(EventDispatcher::class)->dispatch(new ContactPointPurposeAttached(
                $purpose->contactPoint,
                $purpose->purpose,
                [
                    'priority' => $purpose->priority,
                    'is_preferred' => $purpose->is_preferred,
                ],
            ));
        });

        self::deleted(function (ContactPointPurpose $purpose) {
            app(EventDispatcher::class)->dispatch(new ContactPointPurposeDetached(
                $purpose->contactPoint,
                $purpose->purpose,
            ));
        });
    }

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'priority' => 0,
        'is_preferred' => false,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'contact_point_id',
        'purpose',
        'priority',
        'is_preferred',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'is_preferred' => 'boolean',
        ];
    }

    public function getTable(): string
    {
        return TablePrefixer::prefix('contact_point_purposes');
    }

    /**
     * @return BelongsTo<ContactPoint, $this>
     */
    public function contactPoint(): BelongsTo
    {
        return $this->belongsTo(ContactPoint::class);
    }
}
