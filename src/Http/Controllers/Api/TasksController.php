<?php

namespace RadThemes\RadpackCrm\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use RadThemes\RadpackCrm\Models\Task;

class TasksController extends RecordsController
{
    protected function model(): string
    {
        return Task::class;
    }

    protected function filter(Builder $query, Request $request): void
    {
        foreach (['contact_id', 'company_id', 'assigned_to'] as $column) {
            if ($request->filled($column)) {
                $query->where($column, $request->query($column));
            }
        }

        if ($request->query('completed') !== null) {
            $request->boolean('completed') ? $query->whereNotNull('completed_at') : $query->whereNull('completed_at');
        }
    }
}
