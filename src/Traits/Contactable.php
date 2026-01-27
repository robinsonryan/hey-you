<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use RobinsonRyan\HeyYou\Models\Party;

/**
 * Trait for consumer models to integrate with HeyYou.
 *
 * @mixin Model
 */
trait Contactable
{
    public static function bootContactable(): void
    {
        static::created(function (Model $model) {
            /** @var Model&Contactable $model */
            $model->party()->create([
                'display_name_cached' => $model->getDisplayNameForParty(),
            ]);
        });

        static::updated(function (Model $model) {
            /** @var Model&Contactable $model */
            $party = $model->party;
            if ($party !== null) {
                $newDisplayName = $model->getDisplayNameForParty();
                if ($party->display_name_cached !== $newDisplayName) {
                    $party->update(['display_name_cached' => $newDisplayName]);
                }
            }
        });

        static::deleted(function (Model $model) {
            /** @var Model&Contactable $model */
            $party = $model->party;
            if ($party !== null) {
                $party->delete();
            }
        });
    }

    /**
     * @return MorphOne<Party, $this>
     */
    public function party(): MorphOne
    {
        return $this->morphOne(Party::class, 'partyable');
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
