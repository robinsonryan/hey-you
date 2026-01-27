<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Resolver;

use Illuminate\Support\Collection;
use RobinsonRyan\HeyYou\Contracts\ConsentChecker;
use RobinsonRyan\HeyYou\Contracts\ContactResolver;
use RobinsonRyan\HeyYou\Contracts\DncChecker;
use RobinsonRyan\HeyYou\Contracts\EventDispatcher;
use RobinsonRyan\HeyYou\Contracts\Registries\PurposeRegistry;
use RobinsonRyan\HeyYou\Contracts\ScopeHierarchyResolver;
use RobinsonRyan\HeyYou\Events\Resolver\ContactResolved;
use RobinsonRyan\HeyYou\Models\ContactPoint;
use RobinsonRyan\HeyYou\Models\Party;
use RobinsonRyan\HeyYou\Models\RoleAssignment;

final class DefaultContactResolver implements ContactResolver
{
    /**
     * Status ranking order (lower index = better).
     *
     * @var array<string, int>
     */
    private const STATUS_RANK = [
        ContactPoint::STATUS_ACTIVE => 1,
        ContactPoint::STATUS_INACTIVE => 2,
        ContactPoint::STATUS_BOUNCED => 3,
        ContactPoint::STATUS_UNREACHABLE => 4,
    ];

    /**
     * Statuses that exclude contact points from resolution.
     *
     * @var list<string>
     */
    private const EXCLUDED_STATUSES = [
        ContactPoint::STATUS_BLOCKED,
    ];

    public function __construct(
        private readonly ScopeHierarchyResolver $scopeResolver,
        private readonly DncChecker $dncChecker,
        private readonly ConsentChecker $consentChecker,
        private readonly PurposeRegistry $purposeRegistry,
        private readonly EventDispatcher $eventDispatcher,
    ) {}

    public function resolve(ResolverRequest $request): ResolverResult
    {
        $candidates = collect();
        $exclusions = ['dnc' => 0, 'no_consent' => 0, 'status' => 0, 'verification' => 0, 'excluded' => 0];
        $fallbackUsed = false;
        $fallbackPath = [];

        $constraints = $request->getEffectiveConstraints();
        $scopeChain = $this->scopeResolver->resolve($request->getEffectiveScopeParty());

        $isFirstScope = true;

        foreach ($scopeChain as $scope) {
            $scopeCandidates = $this->gatherCandidates($scope, $request);

            foreach ($scopeCandidates as $candidate) {
                $filterResult = $this->applyFilters($candidate, $request, $constraints);

                if ($filterResult['excluded']) {
                    $exclusions[$filterResult['reason']]++;

                    continue;
                }

                $candidates->push($candidate);
            }

            // If fallback is not allowed, stop after the first scope
            if (! $constraints->allowFallback) {
                break;
            }

            // If we have candidates, stop looking (no need for fallback)
            if ($candidates->isNotEmpty()) {
                break;
            }

            // Track fallback usage when moving to the next scope
            $fallbackUsed = true;
            $fallbackPath[] = "party:{$scope->id}";
            $isFirstScope = false;
        }

        $ranked = $this->rank($candidates, $request);
        $limited = $ranked->take($request->limit);

        // Build matches with assigned ranks
        $matches = $limited->values()->map(function (array $candidate, int $index): ResolverMatch {
            return $this->buildMatch($candidate, $index + 1);
        });

        $result = new ResolverResult(
            matches: $matches,
            explanation: new ResolverExplanation(
                candidatesConsidered: $candidates->count() + array_sum($exclusions),
                exclusionSummary: array_filter($exclusions),
                fallbackUsed: $fallbackUsed,
                fallbackPath: $fallbackPath !== [] ? implode(' → ', $fallbackPath) : null,
            ),
        );

        $this->eventDispatcher->dispatch(new ContactResolved($request, $result));

        return $result;
    }

    /**
     * Gather candidate contact points from a scope.
     *
     * @return Collection<int, array{contact_point: ContactPoint, scope: Party, role: string|null, purpose_match: string|null, purpose_priority: int|null}>
     */
    private function gatherCandidates(Party $scope, ResolverRequest $request): Collection
    {
        $candidates = collect();

        // 1. Find role holders for this scope and their contact points
        $roleHolders = $this->findRoleHolders($scope, $request->purpose);

        foreach ($roleHolders as $roleAssignment) {
            $contactPoints = $roleAssignment->party
                ->contactPoints()
                ->where('channel', $request->channel)
                ->get();

            foreach ($contactPoints as $contactPoint) {
                $purposeMatch = $this->matchPurpose($contactPoint, $request->purpose);

                $candidates->push([
                    'contact_point' => $contactPoint,
                    'scope' => $scope,
                    'role' => $roleAssignment->role,
                    'purpose_match' => $purposeMatch['purpose'],
                    'purpose_priority' => $purposeMatch['priority'],
                    'role_priority' => $roleAssignment->priority,
                ]);
            }
        }

        // 2. Find shared contact points owned directly by the scope party
        $scopeContactPoints = $scope->contactPoints()
            ->where('channel', $request->channel)
            ->get();

        foreach ($scopeContactPoints as $contactPoint) {
            $purposeMatch = $this->matchPurpose($contactPoint, $request->purpose);

            $candidates->push([
                'contact_point' => $contactPoint,
                'scope' => $scope,
                'role' => null,
                'purpose_match' => $purposeMatch['purpose'],
                'purpose_priority' => $purposeMatch['priority'],
                'role_priority' => null,
            ]);
        }

        return $candidates;
    }

    /**
     * Find role holders for a given scope and purpose.
     *
     * @return Collection<int, RoleAssignment>
     */
    private function findRoleHolders(Party $scope, string $purpose): Collection
    {
        // Map purpose to expected role names
        $expectedRoles = $this->getPurposeRoles($purpose);

        return RoleAssignment::query()
            ->where('scope_party_id', $scope->id)
            ->whereIn('role', $expectedRoles)
            ->current()
            ->orderBy('priority')
            ->with('party.contactPoints')
            ->get();
    }

    /**
     * Get the roles that correspond to a purpose.
     *
     * @return list<string>
     */
    private function getPurposeRoles(string $purpose): array
    {
        // Common naming convention: purpose + "_contact"
        $roles = ["{$purpose}_contact"];

        // Also check for parent purpose roles
        if ($this->purposeRegistry->exists($purpose)) {
            $parent = $this->purposeRegistry->parent($purpose);
            if ($parent !== null) {
                $roles[] = "{$parent}_contact";
            }
        }

        // Always include generic primary contact role
        $roles[] = 'primary_contact';

        return $roles;
    }

    /**
     * Match a contact point's purposes against the requested purpose.
     *
     * @return array{purpose: string|null, priority: int|null}
     */
    private function matchPurpose(ContactPoint $contactPoint, string $requestedPurpose): array
    {
        $purposes = $contactPoint->purposes;

        // Check for exact match
        $exactMatch = $purposes->firstWhere('purpose', $requestedPurpose);
        if ($exactMatch !== null) {
            return ['purpose' => $requestedPurpose, 'priority' => $exactMatch->priority];
        }

        // Check for parent purpose match
        if ($this->purposeRegistry->exists($requestedPurpose)) {
            $parentPurpose = $this->purposeRegistry->parent($requestedPurpose);
            if ($parentPurpose !== null) {
                $parentMatch = $purposes->firstWhere('purpose', $parentPurpose);
                if ($parentMatch !== null) {
                    return ['purpose' => $parentPurpose, 'priority' => $parentMatch->priority];
                }
            }
        }

        return ['purpose' => null, 'priority' => null];
    }

    /**
     * Apply filters to a candidate.
     *
     * @param  array{contact_point: ContactPoint, scope: Party, role: string|null, purpose_match: string|null, purpose_priority: int|null}  $candidate
     * @return array{excluded: bool, reason: string}
     */
    private function applyFilters(array $candidate, ResolverRequest $request, ResolverConstraints $constraints): array
    {
        $contactPoint = $candidate['contact_point'];

        // Check if explicitly excluded
        if (in_array($contactPoint->id, $constraints->excludeContactPointIds, true)) {
            return ['excluded' => true, 'reason' => 'excluded'];
        }

        // Check status exclusions
        if (in_array($contactPoint->status, self::EXCLUDED_STATUSES, true)) {
            return ['excluded' => true, 'reason' => 'status'];
        }

        // Check DNC
        $dncResult = $this->dncChecker->isBlocked($contactPoint, $request->purpose);
        if ($dncResult->blocked) {
            return ['excluded' => true, 'reason' => 'dnc'];
        }

        // Check verification requirement
        if ($constraints->requireVerified && ! $contactPoint->isCurrentlyVerified()) {
            return ['excluded' => true, 'reason' => 'verification'];
        }

        // Check consent requirement
        if ($constraints->requireConsent && $constraints->consentCategory !== null) {
            $consentResult = $this->consentChecker->hasConsent($contactPoint, $constraints->consentCategory);
            // When consent is required, we need explicit opt-in consent, not just "no denial"
            // Level 'none' means no consent record exists, which should be treated as no consent
            if (! $consentResult->allowed || $consentResult->level === 'none') {
                return ['excluded' => true, 'reason' => 'no_consent'];
            }
        }

        return ['excluded' => false, 'reason' => ''];
    }

    /**
     * Rank candidates according to the fixed priority order.
     *
     * @param  Collection<int, array{contact_point: ContactPoint, scope: Party, role: string|null, purpose_match: string|null, purpose_priority: int|null, role_priority: int|null}>  $candidates
     * @return Collection<int, array{contact_point: ContactPoint, scope: Party, role: string|null, purpose_match: string|null, purpose_priority: int|null, role_priority: int|null}>
     */
    private function rank(Collection $candidates, ResolverRequest $request): Collection
    {
        $scopeChain = $this->scopeResolver->resolve($request->getEffectiveScopeParty());
        $scopeOrder = $scopeChain->pluck('id')->flip();

        return $candidates->sort(function (array $a, array $b) use ($scopeOrder, $request): int {
            $cpA = $a['contact_point'];
            $cpB = $b['contact_point'];

            // 1. Status ranking (active > inactive > bounced > unreachable)
            $statusA = self::STATUS_RANK[$cpA->status] ?? 99;
            $statusB = self::STATUS_RANK[$cpB->status] ?? 99;
            if ($statusA !== $statusB) {
                return $statusA <=> $statusB;
            }

            // 2. Verification (verified > unverified)
            $verifiedA = $cpA->isCurrentlyVerified() ? 0 : 1;
            $verifiedB = $cpB->isCurrentlyVerified() ? 0 : 1;
            if ($verifiedA !== $verifiedB) {
                return $verifiedA <=> $verifiedB;
            }

            // 3. Purpose match (exact > parent > none)
            $purposeRankA = $this->getPurposeRank($a['purpose_match'], $request->purpose);
            $purposeRankB = $this->getPurposeRank($b['purpose_match'], $request->purpose);
            if ($purposeRankA !== $purposeRankB) {
                return $purposeRankA <=> $purposeRankB;
            }

            // 4. Scope specificity (lower in chain = more specific = better)
            $scopeRankA = $scopeOrder->get($a['scope']->id, 999);
            $scopeRankB = $scopeOrder->get($b['scope']->id, 999);
            if ($scopeRankA !== $scopeRankB) {
                return $scopeRankA <=> $scopeRankB;
            }

            // 5. Primary flag (primary > non-primary)
            $primaryA = $cpA->is_primary ? 0 : 1;
            $primaryB = $cpB->is_primary ? 0 : 1;
            if ($primaryA !== $primaryB) {
                return $primaryA <=> $primaryB;
            }

            // 6. Purpose priority (lower = better)
            $ppA = $a['purpose_priority'] ?? PHP_INT_MAX;
            $ppB = $b['purpose_priority'] ?? PHP_INT_MAX;
            if ($ppA !== $ppB) {
                return $ppA <=> $ppB;
            }

            // 7. Role priority (lower = better)
            $rpA = $a['role_priority'] ?? PHP_INT_MAX;
            $rpB = $b['role_priority'] ?? PHP_INT_MAX;
            if ($rpA !== $rpB) {
                return $rpA <=> $rpB;
            }

            // 8. Created date (older = more established = better)
            return $cpA->created_at <=> $cpB->created_at;
        })->values();
    }

    /**
     * Get the rank for a purpose match.
     * 0 = exact match, 1 = parent match, 2 = no match
     */
    private function getPurposeRank(?string $matchedPurpose, string $requestedPurpose): int
    {
        if ($matchedPurpose === null) {
            return 2;
        }

        if ($matchedPurpose === $requestedPurpose) {
            return 0;
        }

        // Must be a parent match
        return 1;
    }

    /**
     * Build a ResolverMatch from a candidate array.
     *
     * @param  array{contact_point: ContactPoint, scope: Party, role: string|null, purpose_match: string|null, purpose_priority: int|null}  $candidate
     */
    private function buildMatch(array $candidate, int $rank): ResolverMatch
    {
        $contactPoint = $candidate['contact_point'];

        return new ResolverMatch(
            contactPoint: $contactPoint,
            owningParty: $contactPoint->party,
            channel: $contactPoint->channel,
            normalizedValue: $contactPoint->value_normalized,
            matchedPurpose: $candidate['purpose_match'],
            matchedRole: $candidate['role'],
            scopeParty: $candidate['scope'],
            flags: [
                'verified' => $contactPoint->isCurrentlyVerified(),
                'is_primary' => $contactPoint->is_primary,
            ],
            rank: $rank,
        );
    }
}
