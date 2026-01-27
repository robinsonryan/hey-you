<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Events\Consent;

use Illuminate\Database\Eloquent\Model;
use RobinsonRyan\HeyYou\Models\ContactPointConsent;
use RobinsonRyan\HeyYou\Models\PartyConsent;

final readonly class ConsentRevoked
{
    /**
     * @param  PartyConsent|ContactPointConsent  $consent
     * @param  string  $level  'party' or 'contact_point'
     */
    public function __construct(
        public Model $consent,
        public string $level,
        public string $purposeCategory,
        public ?string $channel,
    ) {}
}
