<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Checkers;

use RobinsonRyan\HeyYou\Contracts\ConsentChecker;
use RobinsonRyan\HeyYou\Models\ContactPoint;
use RobinsonRyan\HeyYou\Models\ContactPointConsent;
use RobinsonRyan\HeyYou\Models\PartyConsent;
use RobinsonRyan\HeyYou\Support\ConsentResult;

final class DefaultConsentChecker implements ConsentChecker
{
    public function hasConsent(ContactPoint $contactPoint, string $purposeCategory): ConsentResult
    {
        // Check contact point level first (most specific)
        $contactPointConsent = $this->getContactPointConsent($contactPoint, $purposeCategory);

        if ($contactPointConsent !== null) {
            return $this->buildResult($contactPointConsent, 'contact_point');
        }

        // Check party level (channel-specific first, then generic)
        $partyConsent = $this->getPartyConsent($contactPoint, $purposeCategory);

        if ($partyConsent !== null) {
            return $this->buildResult($partyConsent, 'party');
        }

        // No consent record found - default to allowed
        return ConsentResult::none();
    }

    private function getContactPointConsent(ContactPoint $contactPoint, string $purposeCategory): ?ContactPointConsent
    {
        return ContactPointConsent::query()
            ->where('contact_point_id', $contactPoint->id)
            ->where('purpose_category', $purposeCategory)
            ->orderByDesc('captured_at')
            ->first();
    }

    private function getPartyConsent(ContactPoint $contactPoint, string $purposeCategory): ?PartyConsent
    {
        // First try channel-specific consent (more specific)
        $channelSpecific = PartyConsent::query()
            ->where('party_id', $contactPoint->party_id)
            ->where('purpose_category', $purposeCategory)
            ->where('channel', $contactPoint->channel)
            ->orderByDesc('captured_at')
            ->first();

        if ($channelSpecific !== null) {
            return $channelSpecific;
        }

        // Fall back to generic party consent (channel = null)
        return PartyConsent::query()
            ->where('party_id', $contactPoint->party_id)
            ->where('purpose_category', $purposeCategory)
            ->whereNull('channel')
            ->orderByDesc('captured_at')
            ->first();
    }

    private function buildResult(ContactPointConsent|PartyConsent $consent, string $level): ConsentResult
    {
        $allowed = $consent->status === ContactPointConsent::STATUS_OPTED_IN
            || $consent->status === PartyConsent::STATUS_OPTED_IN;

        if ($allowed) {
            return ConsentResult::allowed($level, $consent->status, $consent->captured_at);
        }

        return ConsentResult::denied($level, $consent->status, $consent->captured_at);
    }
}
