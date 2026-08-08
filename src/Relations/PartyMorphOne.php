<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Relations;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Query\Expression as QueryExpression;
use RobinsonRyan\HeyYou\Models\Party;

/**
 * The consumer -> Party morph, with the consumer's key coerced to text.
 *
 * `heyyou_parties.partyable_id` is a varchar, because the package's own models
 * carry UUID7 keys. A consumer model on an auto-incrementing bigint key binds
 * its key as an integer, and PostgreSQL refuses `character varying = bigint`
 * outright — it has no implicit cast between the two, so every read through the
 * relation fails with SQLSTATE[42883] rather than silently coercing the way
 * SQLite did. This subclass closes that gap wherever the two sides meet: bound
 * values become strings, and the correlated column of an existence query is
 * wrapped in a cast.
 *
 * Consumers that follow the documented UUID7 convention already have a string
 * key, so nothing below changes their queries.
 *
 * @template TDeclaringModel of Model
 *
 * @extends MorphOne<Party, TDeclaringModel>
 */
final class PartyMorphOne extends MorphOne
{
    /**
     * The parent key as bound into `where partyable_id = ?`, into eager-load
     * dictionaries, and onto the foreign key of a party being created.
     */
    public function getParentKey(): ?string
    {
        $key = parent::getParentKey();

        return $key === null ? null : (string) $key;
    }

    /**
     * Qualify the parent key for `has()` / `whereHas()`, where the comparison
     * happens column-to-column in SQL and no binding is involved.
     */
    public function getQualifiedParentKeyName(): string|Expression
    {
        $name = parent::getQualifiedParentKeyName();

        if ($this->parent->getKeyType() === 'string') {
            return $name;
        }

        $column = $this->parent->getConnection()->getQueryGrammar()->wrap($name);

        // The wrapped value is the parent's own table and key name, never user
        // input, so there is nothing here for a caller to inject through.
        // @phpstan-ignore argument.type
        return new QueryExpression('cast('.$column.' as varchar)');
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

    /**
     * The keys gathered for an eager load's `where partyable_id in (?, ?)`.
     *
     * @param  array<int, TDeclaringModel>  $models
     * @param  string|null  $key
     * @return array<int, string|null>
     */
    protected function getKeys(array $models, $key = null): array
    {
        return array_map(
            static fn (mixed $value): ?string => $value === null ? null : (string) $value,
            parent::getKeys($models, $key),
        );
    }
}
