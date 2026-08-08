<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Relations\Concerns;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression as QueryExpression;

/**
 * Shared coercion for relations that compare a consumer's primary key against
 * `heyyou_parties.partyable_id`.
 *
 * That column is a varchar, because the package's own models carry UUID7 keys
 * and the morph has to hold any consumer's key shape. PostgreSQL is strict about
 * the comparison: there is no implicit cast from `bigint`, and none from `uuid`
 * either, so `partyable_id = <consumer key>` fails outright with SQLSTATE[42883]
 * ("operator does not exist") rather than coercing the way SQLite did.
 *
 * There are two distinct halves to the fix, and they need different treatment:
 *
 * - **Bound values** — `where partyable_id = ?` and the `in (?, ?)` of an eager
 *   load. A string binding compares cleanly against a varchar column whatever
 *   the consumer's key type is, so these are stringified unconditionally.
 * - **Correlated columns** — the `whereColumn` of a `has()` / `whereHas()`
 *   existence query, where both sides are columns and there is no binding for
 *   PostgreSQL to coerce. Here the consumer's column is wrapped in a cast.
 *
 * The cast is unconditional. An earlier version guarded it on
 * `getKeyType() !== 'string'`, on the assumption that the documented UUID7
 * consumer needed no help — but Eloquent reports `'string'` for a native
 * PostgreSQL `uuid` column just as it does for a `varchar` one, and `uuid` has
 * no implicit cast to `character varying`. That guard therefore left
 * `User::has('party')` throwing for exactly the consumer shape this package
 * documents. The cast sits on the outer, correlated side of the subquery, where
 * it is a per-row scalar; the index that carries the lookup is the one on
 * `heyyou_parties (partyable_type, partyable_id)`, which stays usable.
 */
trait CoercesConsumerKeyToText
{
    /**
     * A single consumer key as it should be bound against a varchar column.
     */
    protected function stringifyConsumerKey(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    /**
     * A list of consumer keys as they should be bound into an `in (...)` list.
     *
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, string|null>
     */
    protected function stringifyConsumerKeys(array $values): array
    {
        return array_map(
            fn (mixed $value): ?string => $this->stringifyConsumerKey($value),
            $values,
        );
    }

    /**
     * A consumer's qualified key column, cast to text for a column-to-column
     * comparison against `partyable_id`.
     */
    protected function consumerKeyAsText(Model $consumer, string $qualifiedColumn): Expression
    {
        $column = $consumer->getConnection()->getQueryGrammar()->wrap($qualifiedColumn);

        // The wrapped value is the consumer's own table and key name, never user
        // input, so there is nothing here for a caller to inject through.
        // @phpstan-ignore argument.type
        return new QueryExpression('cast('.$column.' as varchar)');
    }
}
