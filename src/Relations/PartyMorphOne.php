<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Relations;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use RobinsonRyan\HeyYou\Models\Party;
use RobinsonRyan\HeyYou\Relations\Concerns\CoercesConsumerKeyToText;

/**
 * The consumer -> Party morph, with the consumer's key coerced to text.
 *
 * `heyyou_parties.partyable_id` is a varchar; the consumer's key is whatever the
 * consumer chose. CoercesConsumerKeyToText explains why PostgreSQL will not
 * bridge the two on its own — this class wires that coercion into the places a
 * MorphOne touches the boundary.
 *
 * @template TDeclaringModel of Model
 *
 * @extends MorphOne<Party, TDeclaringModel>
 */
final class PartyMorphOne extends MorphOne
{
    use CoercesConsumerKeyToText;

    /**
     * The parent key as bound into `where partyable_id = ?`, into eager-load
     * dictionaries, and onto the `partyable_id` of a party being created.
     *
     * That last use is what makes this load-bearing rather than cosmetic: the
     * value is written straight onto the new Party's attribute, so an
     * integer-keyed consumer would otherwise hold an int in memory where the
     * column — and every reader of it — has a string.
     */
    public function getParentKey(): ?string
    {
        $key = parent::getParentKey();

        return $key === null ? null : (string) $key;
    }

    /**
     * Qualify the parent key for `has()` / `whereHas()`, where the comparison
     * happens column-to-column in SQL and no binding is involved.
     *
     * Laravel's PHPDoc narrows this to `string`, but every caller hands the
     * result to the query grammar's `wrap()`, which accepts an Expression just
     * as readily — that is how `DB::raw()` columns work at all.
     *
     * @phpstan-ignore method.childReturnType
     */
    public function getQualifiedParentKeyName(): Expression
    {
        return $this->consumerKeyAsText($this->parent, parent::getQualifiedParentKeyName());
    }

    /**
     * Eloquent optimises an eager load on an integer primary key into
     * `whereIntegerInRaw`, which inlines bare integers into the SQL — past the
     * binding layer, and so past the string coercion above. Force the ordinary
     * bound `whereIn` instead.
     */
    protected function whereInMethod(Model $model, $key): string
    {
        return 'whereIn';
    }
}
