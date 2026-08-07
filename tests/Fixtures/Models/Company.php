<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use RobinsonRyan\HeyYou\Traits\ConfiguresIdentifiers;
use RobinsonRyan\HeyYou\Traits\Contactable;

/**
 * @property string $id
 * @property string $legal_name
 */
final class Company extends Model
{
    use ConfiguresIdentifiers;
    use Contactable;

    protected $table = 'companies';

    protected $guarded = [];

    public function getDisplayNameForParty(): string
    {
        return $this->legal_name;
    }
}
