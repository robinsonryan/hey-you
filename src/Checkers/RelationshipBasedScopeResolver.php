<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Checkers;

use Illuminate\Support\Collection;
use RobinsonRyan\HeyYou\Contracts\ScopeHierarchyResolver;
use RobinsonRyan\HeyYou\Models\Party;
use RobinsonRyan\HeyYou\Models\PartyRelationship;

final class RelationshipBasedScopeResolver implements ScopeHierarchyResolver
{
    /**
     * Relationship types that indicate hierarchical structure (from child to parent).
     *
     * @var list<string>
     */
    private const HIERARCHICAL_RELATIONSHIP_TYPES = [
        'location_of',
        'member_of',
        'parent_of',
    ];

    /**
     * Maximum depth to traverse to prevent infinite loops.
     */
    private const MAX_DEPTH = 10;

    /**
     * @return Collection<int, Party>
     */
    public function resolve(Party $startingScope): Collection
    {
        $hierarchy = collect([$startingScope]);
        $currentParty = $startingScope;
        $visitedIds = [$startingScope->id];
        $depth = 0;

        while ($depth < self::MAX_DEPTH) {
            $parentParty = $this->findParentScope($currentParty, $visitedIds);

            if ($parentParty === null) {
                break;
            }

            $hierarchy->push($parentParty);
            $visitedIds[] = $parentParty->id;
            $currentParty = $parentParty;
            $depth++;
        }

        return $hierarchy;
    }

    /**
     * Find the parent scope for a given party by traversing relationships.
     *
     * @param  list<int>  $visitedIds
     */
    private function findParentScope(Party $party, array $visitedIds): ?Party
    {
        // Look for outgoing hierarchical relationships (this party -> parent)
        $relationship = PartyRelationship::query()
            ->where('from_party_id', $party->id)
            ->whereIn('relationship_type', self::HIERARCHICAL_RELATIONSHIP_TYPES)
            ->whereNotIn('to_party_id', $visitedIds)
            ->current()
            ->with('toParty')
            ->orderByRaw($this->getRelationshipTypePriorityOrder())
            ->first();

        return $relationship?->toParty;
    }

    /**
     * Get SQL for ordering relationship types by priority.
     * location_of is most specific, then member_of, then parent_of.
     */
    private function getRelationshipTypePriorityOrder(): string
    {
        return "CASE relationship_type
            WHEN 'location_of' THEN 1
            WHEN 'member_of' THEN 2
            WHEN 'parent_of' THEN 3
            ELSE 4
        END";
    }
}
