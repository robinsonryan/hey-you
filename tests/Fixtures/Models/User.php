<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use RobinsonRyan\HeyYou\Traits\ConfiguresIdentifiers;
use RobinsonRyan\HeyYou\Traits\Contactable;

/**
 * @property string $id
 * @property string $name
 * @property string $email
 */
final class User extends Model
{
    use ConfiguresIdentifiers;
    use Contactable;

    protected $table = 'users';

    protected $guarded = [];

    public function getDisplayNameForParty(): string
    {
        return $this->name;
    }
}
