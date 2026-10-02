<?php

namespace RadThemes\RadpackCrm\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use RadThemes\RadpackCrm\Support\Payload;
use Statamic\CP\PublishForm;

/**
 * REST endpoints for a blueprint-backed record. Writes go through the record's blueprint,
 * so the API validates exactly like the Control Panel and supports custom fields.
 */
abstract class RecordsController
{
    /**
     * @return class-string<Model>
     */
    abstract protected function model(): string;

    /**
     * Apply list filters from the query string.
     */
    protected function filter(Builder $query, Request $request): void {}

    /**
     * Map API input (e.g. "company_id") to blueprint values (e.g. "company" => [id]).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function toBlueprintValues(array $input): array
    {
        foreach (['company', 'contact'] as $relation) {
            if (array_key_exists("{$relation}_id", $input)) {
                $input[$relation] = $input["{$relation}_id"] ? [$input["{$relation}_id"]] : [];
                unset($input["{$relation}_id"]);
            }
        }

        foreach (['owner_id' => 'owner', 'assigned_to' => 'assigned_to'] as $key => $handle) {
            if (array_key_exists($key, $input)) {
                $input[$handle] = $input[$key] ? [$input[$key]] : [];
                if ($key !== $handle) {
                    unset($input[$key]);
                }
            }
        }

        // Custom fields may be sent flat or inside "fields".
        if (is_array($input['fields'] ?? null)) {
            $input = array_merge($input['fields'], $input);
        }
        unset($input['fields']);

        return $input;
    }

    public function index(Request $request): JsonResponse
    {
        $model = $this->model();
        $query = $model::query();

        if ($since = $request->query('updated_since')) {
            $query->where('updated_at', '>=', Carbon::parse($since));
        }

        $this->filter($query, $request);

        $page = $query->orderBy('id', $request->query('order') === 'asc' ? 'asc' : 'desc')
            ->paginate(min(100, max(1, (int) $request->query('per_page', 25))));

        return response()->json([
            'data' => collect($page->items())->map(fn ($record) => Payload::for($record))->all(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => Payload::for($this->find($id))]);
    }

    public function store(Request $request): JsonResponse
    {
        $model = $this->model();
        $record = new $model;

        $record->fillFromBlueprint(PublishForm::make($model::blueprint())->submit($this->toBlueprintValues($request->all())))->save();

        return response()->json(['data' => Payload::for($record->fresh())], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $record = $this->find($id);
        $values = array_merge($record->blueprintValues(), $this->toBlueprintValues($request->all()));

        $record->fillFromBlueprint(PublishForm::make($record::blueprint())->submit($values))->save();

        return response()->json(['data' => Payload::for($record->fresh())]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->find($id)->delete();

        return response()->json(null, 204);
    }

    protected function find(int $id): Model
    {
        return $this->model()::findOrFail($id);
    }
}
