<?php

namespace RadThemes\RadpackCrm\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use RadThemes\RadpackCrm\Models\Company;

class CompaniesController extends RecordsController
{
    protected function model(): string
    {
        return Company::class;
    }

    protected function filter(Builder $query, Request $request): void
    {
        if ($search = $request->query('search')) {
            $query->where('name', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $search).'%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
    }
}
