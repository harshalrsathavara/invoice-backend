<?php

namespace App\Models\Concerns;

/**
 * Fills in a column the schema declares NOT NULL when a blank field arrives.
 *
 * These columns are NOT NULL with a default, because the handset has always
 * stored an unfilled field as '' or 0. A browser, though, submits every box on
 * a form, so an optional one left empty arrives as '' and then — through
 * Laravel's ConvertEmptyStringsToNull — as null. Writing that null is a
 * constraint violation.
 *
 * Doing it on save rather than in a controller covers every write path at once:
 * the panel, the API, sync and import all reach the same tables, and only the
 * import happened to guard against it by hand.
 *
 * Only a column actually being written is touched, so leaving a key out still
 * stores the column's own default.
 */
trait NotNullDefaults
{
    public static function bootNotNullDefaults(): void
    {
        static::saving(fn ($model) => $model->applyNotNullDefaults());
    }

    /**
     * The map itself lives on the model, as $notNullDefaults — column to the
     * value to use instead of null. PHP refuses a trait property that a using
     * class redeclares with a different value, so the trait reads it rather
     * than declaring a default of its own.
     */
    public function applyNotNullDefaults(): void
    {
        foreach ($this->notNullDefaults ?? [] as $column => $fallback) {
            if (array_key_exists($column, $this->attributes) && $this->attributes[$column] === null) {
                $this->attributes[$column] = $fallback;
            }
        }
    }
}
