<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use RobinsonRyan\HeyYou\Traits\Contactable;

/**
 * A consumer model on a plain auto-incrementing bigint primary key — the shape
 * of an application that predates (or simply never adopted) the package's UUID7
 * convention. Deliberately does NOT use ConfiguresIdentifiers.
 *
 * @property int $id
 * @property string $name
 */
final class LegacyAccount extends Model
{
    use Contactable;

    protected $table = 'legacy_accounts';

    protected $guarded = [];

    public function getDisplayNameForParty(): string
    {
        return $this->name;
    }
}
