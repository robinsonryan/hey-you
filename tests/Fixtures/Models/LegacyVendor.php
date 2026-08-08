<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use RobinsonRyan\HeyYou\Traits\Contactable;

/**
 * A second consumer model on a plain auto-incrementing bigint primary key.
 *
 * Its only reason to exist is collision: two integer-keyed consumer tables both
 * count from 1, so a LegacyAccount and a LegacyVendor can hold the *same* key
 * value and therefore the same `heyyou_parties.partyable_id` string. Only the
 * `partyable_type` constraint keeps one from reading the other's rows, and a
 * UUID-keyed fixture can never prove that — UUIDs do not collide.
 *
 * @property int $id
 * @property string $name
 */
final class LegacyVendor extends Model
{
    use Contactable;

    protected $table = 'legacy_vendors';

    protected $guarded = [];

    public function getDisplayNameForParty(): string
    {
        return $this->name;
    }
}
