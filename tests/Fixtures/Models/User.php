<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use RobinsonRyan\HeyYou\Traits\Contactable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 */
final class User extends Model
{
    use Contactable;

    protected $table = 'users';

    protected $guarded = [];

    public function getDisplayNameForParty(): string
    {
        return $this->name;
    }
}
