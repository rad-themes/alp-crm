<?php

namespace RadThemes\RadpackCrm\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RadThemes\RadpackCrm\Csv\CsvExporter;
use RadThemes\RadpackCrm\Csv\CsvImporter;
use RadThemes\RadpackCrm\Models\Company;
use RadThemes\RadpackCrm\Models\Contact;
use RadThemes\RadpackCrm\Models\Segment;
use Statamic\Http\Controllers\CP\CpController;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportExportController extends CpController
{
    public function create(Request $request): Response
    {
        $this->authorize('edit crm');

        return Inertia::render('radpack-crm::Import', [
            'type' => $request->query('type') === 'companies' ? 'companies' : 'contacts',
            'uploadUrl' => cp_route('radpack-crm.import.upload'),
            'step' => 'upload',
        ]);
    }

    public function upload(Request $request): RedirectResponse
    {
        $this->authorize('edit crm');

        $data = $request->validate([
            'type' => ['required', Rule::in(['contacts', 'companies'])],
            'file' => ['required', 'file', 'max:20480', 'mimes:csv,txt'],
        ]);

        $token = Str::random(32);
        File::ensureDirectoryExists(self::directory());
        $data['file']->move(self::directory(), "{$token}.csv");

        return redirect()->to(cp_route('radpack-crm.import.map', ['token' => $token, 'type' => $data['type']]));
    }

    public function map(Request $request, string $token): Response
    {
        $this->authorize('edit crm');

        $type = $request->query('type') === 'companies' ? 'companies' : 'contacts';
        $preview = CsvImporter::preview(self::path($token));

        return Inertia::render('radpack-crm::Import', [
            'type' => $type,
            'step' => 'map',
            'preview' => $preview,
            'mapping' => CsvImporter::guess($type, $preview['headers']),
            'targets' => collect(CsvImporter::targets($type))->map(fn ($label, $handle) => ['value' => $handle, 'label' => $label])->values(),
            'importUrl' => cp_route('radpack-crm.import.run', $token),
            'cancelUrl' => cp_route('radpack-crm.import.create', ['type' => $type]),
        ]);
    }

    public function run(Request $request, string $token): RedirectResponse
    {
        $this->authorize('edit crm');

        $data = $request->validate([
            'type' => ['required', Rule::in(['contacts', 'companies'])],
            'mapping' => ['required', 'array'],
            'mapping.*' => ['nullable', Rule::in(array_keys(CsvImporter::targets($request->input('type') === 'companies' ? 'companies' : 'contacts')))],
            'update' => ['boolean'],
            'tags' => ['array'],
            'tags.*' => ['string', 'max:100'],
            'status' => ['nullable', 'string', 'max:50'],
        ]);

        $path = self::path($token);
        @set_time_limit(0);

        $result = CsvImporter::import($data['type'], $path, $data['mapping'], $data);
        File::delete($path);

        $message = __('Imported: :created created, :updated updated, :skipped skipped.', collect($result)->except('errors')->all());

        return redirect()->to(cp_route("radpack-crm.{$data['type']}.index"))
            ->with($result['errors'] ? 'info' : 'success', $result['errors'] ? $message.' '.implode(' · ', array_slice($result['errors'], 0, 5)) : $message);
    }

    public function export(Request $request, string $type): StreamedResponse
    {
        $this->authorize('view crm');

        abort_unless(in_array($type, ['contacts', 'companies'], true), 404);

        $query = $type === 'companies' ? Company::query() : Contact::query();
        $name = $type;

        if ($type === 'contacts' && $segment = Segment::find($request->query('segment'))) {
            $query = $segment->contacts();
            $name = Str::slug($segment->name);
        }

        return CsvExporter::download($type, $query, "{$name}-".now()->format('Y-m-d').'.csv');
    }

    private static function directory(): string
    {
        return storage_path('app/radpack-crm/imports');
    }

    private static function path(string $token): string
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{32}$/', $token) && is_file($path = self::directory()."/{$token}.csv"), 404);

        return $path;
    }
}
