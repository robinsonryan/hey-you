<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use RobinsonRyan\HeyYou\Traits\Contactable;

/**
 * @property int $id
 * @property string $legal_name
 */
final class Company extends Model
{
    use Contactable;

    protected $table = 'companies';

    protected $guarded = [];

    public function getDisplayNameForParty(): string
    {
        return $this->legal_name;
    }
}
