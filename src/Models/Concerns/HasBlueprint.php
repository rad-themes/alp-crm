<?php

namespace RadThemes\RadpackCrm\Models\Concerns;

use Illuminate\Support\Arr;
use Statamic\Facades\Blueprint as BlueprintFacade;
use Statamic\Fields\Blueprint;

/**
 * Maps a model to an editable addon blueprint: known handles are table columns,
 * every other field (custom fields added in the blueprint editor) lives in the `data` JSON column.
 */
trait HasBlueprint
{
    abstract public static function blueprintHandle(): string;

    /**
     * Blueprint handles stored as real columns.
     *
     * @return array<int, string>
     */
    abstract protected function blueprintColumns(): array;

    public static function blueprint(): Blueprint
    {
        return BlueprintFacade::find('radpack-crm::'.static::blueprintHandle());
    }

    /**
     * The label for a status option as defined in the blueprint (so custom statuses read nicely).
     */
    public function statusLabel(?string $status): string
    {
        return static::blueprint()->field('status')?->get('options')[$status] ?? ucfirst((string) $status);
    }

    /**
     * @return array<string, mixed>
     */
    public function blueprintValues(): array
    {
        return array_merge(
            (array) $this->data,
            Arr::only($this->attributesToArray(), $this->blueprintColumns()),
            $this->relationBlueprintValues(),
        );
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function fillFromBlueprint(array $values): static
    {
        $relations = $this->relationBlueprintHandles();
        $columns = Arr::only($values, $this->blueprintColumns());

        $this->forceFill($columns);

        // Only fields in the blueprint are replaced; anything else in `data` (a removed field,
        // an import or integration value) is kept.
        $blueprintHandles = static::blueprint()->fields()->all()->keys()->all();
        $submitted = Arr::except(Arr::only($values, $blueprintHandles), [...$this->blueprintColumns(), ...$relations]);

        $this->data = array_filter(
            array_merge(Arr::except((array) $this->data, $blueprintHandles), $submitted),
            fn ($value) => $value !== null && $value !== [] && $value !== '',
        ) ?: null;

        $this->fillRelationsFromBlueprint(Arr::only($values, $relations));

        return $this;
    }

    /**
     * Values for blueprint fields backed by relationships (e.g. company, owner, tags).
     *
     * @return array<string, mixed>
     */
    protected function relationBlueprintValues(): array
    {
        return [];
    }

    /**
     * @return array<int, string>
     */
    protected function relationBlueprintHandles(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    protected function fillRelationsFromBlueprint(array $values): void
    {
        //
    }
}
