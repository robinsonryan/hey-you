<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use RobinsonRyan\HeyYou\Support\TablePrefixer;

/**
 * @property int $id
 * @property int $contact_point_id
 * @property string $status
 * @property string $method
 * @property array<string, mixed>|null $evidence
 * @property Carbon $initiated_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ContactPoint $contactPoint
 */
final class VerificationEvent extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'contact_point_id',
        'status',
        'method',
        'evidence',
        'initiated_at',
        'completed_at',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'evidence' => 'array',
            'initiated_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return TablePrefixer::prefix('verification_events');
    }

    /**
     * @return BelongsTo<ContactPoint, $this>
     */
    public function contactPoint(): BelongsTo
    {
        return $this->belongsTo(ContactPoint::class);
    }
}
