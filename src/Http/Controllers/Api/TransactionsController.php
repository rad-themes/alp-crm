<?php

namespace RadThemes\RadpackCrm\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use RadThemes\RadpackCrm\Models\Transaction;

class TransactionsController extends RecordsController
{
    protected function model(): string
    {
        return Transaction::class;
    }

    protected function filter(Builder $query, Request $request): void
    {
        foreach (['contact_id', 'company_id', 'invoice_id', 'status', 'type', 'external_id'] as $column) {
            if ($request->filled($column)) {
                $query->where($column, $request->query($column));
            }
        }
    }
}
