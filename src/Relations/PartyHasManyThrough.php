<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Relations;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use RobinsonRyan\HeyYou\Models\Party;
use RobinsonRyan\HeyYou\Relations\Concerns\CoercesConsumerKeyToText;

/**
 * A consumer -> Party -> party-owned rows relation.
 *
 * Two things separate this from a plain `hasManyThrough()`:
 *
 * 1. **The morph type is part of the relation, not the call site.** The hop runs
 *    through `heyyou_parties`, whose `partyable_id` says nothing about which
 *    consumer table a key came from. Two consumer types on auto-incrementing
 *    keys both count from 1, so their `partyable_id` strings collide exactly —
 *    without `partyable_type` each would read the other's contact points and
 *    addresses. Constraining it here means no caller can forget to.
 * 2. **The consumer's key is coerced to text** wherever it meets the varchar
 *    `partyable_id`; see CoercesConsumerKeyToText for why PostgreSQL needs the
 *    help, and PartyMorphOne for the same wiring on the direct morph.
 *
 * @template TRelatedModel of Model
 * @template TDeclaringModel of Model
 *
 * @extends HasManyThrough<TRelatedModel, Party, TDeclaringModel>
 */
final class PartyHasManyThrough extends HasManyThrough
{
    use CoercesConsumerKeyToText;

    /**
     * Restrict the hop to parties belonging to this consumer's morph type.
     *
     * `addConstraints()` runs from the Relation constructor on every path —
     * including under `noConstraints()`, which is how eager loads and existence
     * queries build their relation — so the type filter is applied once, always.
     * An existence query picks it up because `addHasWhere()` merges the
     * relation's own constraints into the `exists` subquery.
     */
    public function addConstraints(): void
    {
        parent::addConstraints();

        $this->getRelationQuery()->where(
            $this->throughParent->qualifyColumn('partyable_type'),
            $this->farParent->getMorphClass(),
        );
    }

    /**
     * Qualify the consumer's key for `has()` / `whereHas()`, where the two sides
     * are compared column-to-column and no binding is involved.
     *
     * Laravel's PHPDoc narrows this to `string`, but the only caller hands the
     * result to `whereColumn()`, which wraps it through the query grammar and so
     * accepts an Expression just as readily.
     *
     * @phpstan-ignore method.childReturnType
     */
    public function getQualifiedLocalKeyName(): Expression
    {
        return $this->consumerKeyAsText($this->farParent, parent::getQualifiedLocalKeyName());
    }

    /**
     * Eloquent optimises an eager load on an integer primary key into
     * `whereIntegerInRaw`, which inlines bare integers into the SQL — past the
     * binding layer, and so past the string coercion below. Force the ordinary
     * bound `whereIn` instead.
     */
    protected function whereInMethod(Model $model, $key): string
    {
        return 'whereIn';
    }
}
