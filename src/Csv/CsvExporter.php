<?php

namespace RadThemes\AlpCrm\Csv;

use Illuminate\Database\Eloquent\Builder;
use RadThemes\AlpCrm\Models\Company;
use RadThemes\AlpCrm\Models\Contact;
use Statamic\Facades\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExporter
{
    public static function download(string $type, Builder $query, string $filename): StreamedResponse
    {
        $handles = array_keys(CsvImporter::targets($type));

        return response()->streamDownload(function () use ($type, $query, $handles) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\u{FEFF}");
            fputcsv($out, array_merge(['id'], $handles, ['created_at']), ',', '"', '');

            $query->with($type === 'contacts' ? ['company', 'tags', 'aliases'] : ['tags'])->chunkById(500, function ($records) use ($out, $handles) {
                foreach ($records as $record) {
                    fputcsv($out, array_map([self::class, 'safe'], array_merge(
                        [$record->id],
                        array_map(fn ($handle) => self::value($record, $handle), $handles),
                        [$record->created_at?->toDateTimeString()],
                    )), ',', '"', '');
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private static function value(Contact|Company $record, string $handle): string
    {
        $value = match ($handle) {
            'company' => $record instanceof Contact ? $record->company?->name : null,
            'tags' => $record->tags->pluck('name')->implode(', '),
            'aliases' => $record instanceof Contact ? $record->aliases->pluck('email')->implode(', ') : null,
            'owner' => $record->owner_id ? User::find($record->owner_id)?->email() : null,
            default => in_array($handle, ['first_name', 'last_name', 'email', 'phone', 'status', 'name', 'website'], true) ? $record->{$handle} : ($record->data[$handle] ?? null),
        };

        return is_array($value) ? implode(', ', array_filter($value, 'is_scalar')) : (string) $value;
    }

    /**
     * Stop spreadsheet apps from running cell values as formulas.
     */
    public static function safe(mixed $value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
