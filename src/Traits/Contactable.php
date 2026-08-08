<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use RobinsonRyan\HeyYou\Models\Party;
use RobinsonRyan\HeyYou\Relations\PartyMorphOne;

/**
 * Trait for consumer models to integrate with HeyYou.
 *
 * @mixin Model
 */
trait Contactable
{
    public static function bootContactable(): void
    {
        static::created(function (Model $model): void {
            /** @var static $model */
            $model->party()->create([
                'display_name_cached' => $model->getDisplayNameForParty(),
            ]);
        });

        static::updated(function (Model $model): void {
            /** @var static $model */
            $party = $model->party;
            if ($party !== null) {
                $newDisplayName = $model->getDisplayNameForParty();
                if ($party->display_name_cached !== $newDisplayName) {
                    $party->update(['display_name_cached' => $newDisplayName]);
                }
            }
        });

        static::deleted(function (Model $model): void {
            /** @var static $model */
            $party = $model->party;
            if ($party !== null) {
                $party->delete();
            }
        });
    }

    /**
     * The party record for this consumer.
     *
     * Built by hand rather than through `morphOne()` so the relation is a
     * PartyMorphOne, which keeps the comparison against the varchar
     * `partyable_id` column working for consumers on integer primary keys.
     *
     * @return MorphOne<Party, $this>
     */
    public function party(): MorphOne
    {
        $party = $this->newRelatedInstance(Party::class);
        $table = $party->getTable();

        return new PartyMorphOne(
            $party->newQuery(),
            $this,
            $table.'.partyable_type',
            $table.'.partyable_id',
            $this->getKeyName(),
        );
    }

    /**
     * Get the display name for the party record.
     * Override in consumer model to customize.
     */
    public function getDisplayNameForParty(): string
    {
        /** @var Model $this */
        if (property_exists($this, 'name') || isset($this->attributes['name'])) {
            return (string) $this->getAttribute('name');
        }

        if (property_exists($this, 'title') || isset($this->attributes['title'])) {
            return (string) $this->getAttribute('title');
        }

        return (string) $this->getKey();
    }
}
