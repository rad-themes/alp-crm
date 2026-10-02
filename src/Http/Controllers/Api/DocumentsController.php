<?php

namespace RadThemes\RadpackCrm\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RadThemes\RadpackCrm\Models\Invoice;
use RadThemes\RadpackCrm\Models\Quote;
use RadThemes\RadpackCrm\Support\Payload;

/**
 * Read-only quotes and invoices (create them in the Control Panel, where line items are edited).
 */
class DocumentsController
{
    public function index(Request $request, string $type): JsonResponse
    {
        $query = ($type === 'invoices' ? Invoice::query() : Quote::query())->with('items')->latest('id');

        foreach (['contact_id', 'company_id', 'status'] as $column) {
            if ($request->filled($column)) {
                $query->where($column, $request->query($column));
            }
        }

        $page = $query->paginate(min(100, max(1, (int) $request->query('per_page', 25))));

        return response()->json([
            'data' => collect($page->items())->map(fn ($document) => Payload::document($document))->all(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    public function show(string $type, int $id): JsonResponse
    {
        $document = ($type === 'invoices' ? Invoice::query() : Quote::query())->findOrFail($id);

        return response()->json(['data' => Payload::document($document)]);
    }
}
