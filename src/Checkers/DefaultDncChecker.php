<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Checkers;

use RobinsonRyan\HeyYou\Contracts\DncChecker;
use RobinsonRyan\HeyYou\Models\ContactPoint;
use RobinsonRyan\HeyYou\Models\DoNotContact;
use RobinsonRyan\HeyYou\Support\DncResult;

final class DefaultDncChecker implements DncChecker
{
    public function isBlocked(ContactPoint $contactPoint, ?string $purpose = null): DncResult
    {
        // Check from most specific to least specific
        // 1. Specific contact point
        $rule = $this->findContactPointRule($contactPoint);
        if ($rule !== null) {
            return DncResult::blocked('contact_point', $rule->reason, $rule);
        }

        // 2. Channel + purpose combination (if purpose provided)
        if ($purpose !== null) {
            $rule = $this->findChannelPurposeRule($contactPoint, $purpose);
            if ($rule !== null) {
                return DncResult::blocked('channel_purpose', $rule->reason, $rule);
            }
        }

        // 3. Purpose specific (if purpose provided)
        if ($purpose !== null) {
            $rule = $this->findPurposeRule($contactPoint, $purpose);
            if ($rule !== null) {
                return DncResult::blocked('purpose', $rule->reason, $rule);
            }
        }

        // 4. Channel specific
        $rule = $this->findChannelRule($contactPoint);
        if ($rule !== null) {
            return DncResult::blocked('channel', $rule->reason, $rule);
        }

        // 5. Party-wide DNC
        $rule = $this->findPartyRule($contactPoint);
        if ($rule !== null) {
            return DncResult::blocked('party', $rule->reason, $rule);
        }

        return DncResult::allowed();
    }

    private function findContactPointRule(ContactPoint $contactPoint): ?DoNotContact
    {
        return DoNotContact::query()
            ->where('party_id', $contactPoint->party_id)
            ->where('contact_point_id', $contactPoint->id)
            ->active()
            ->first();
    }

    private function findChannelPurposeRule(ContactPoint $contactPoint, string $purpose): ?DoNotContact
    {
        return DoNotContact::query()
            ->where('party_id', $contactPoint->party_id)
            ->whereNull('contact_point_id')
            ->where('channel', $contactPoint->channel)
            ->where('purpose', $purpose)
            ->active()
            ->first();
    }

    private function findPurposeRule(ContactPoint $contactPoint, string $purpose): ?DoNotContact
    {
        return DoNotContact::query()
            ->where('party_id', $contactPoint->party_id)
            ->whereNull('contact_point_id')
            ->whereNull('channel')
            ->where('purpose', $purpose)
            ->active()
            ->first();
    }

    private function findChannelRule(ContactPoint $contactPoint): ?DoNotContact
    {
        return DoNotContact::query()
            ->where('party_id', $contactPoint->party_id)
            ->whereNull('contact_point_id')
            ->where('channel', $contactPoint->channel)
            ->whereNull('purpose')
            ->active()
            ->first();
    }

    private function findPartyRule(ContactPoint $contactPoint): ?DoNotContact
    {
        return DoNotContact::query()
            ->where('party_id', $contactPoint->party_id)
            ->whereNull('contact_point_id')
            ->whereNull('channel')
            ->whereNull('purpose')
            ->active()
            ->first();
    }
}
