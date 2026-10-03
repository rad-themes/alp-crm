<?php

namespace RadThemes\AlpCrm\Csv;

use Illuminate\Support\Str;
use RadThemes\AlpCrm\Models\Company;
use RadThemes\AlpCrm\Models\Contact;
use SplFileObject;

/**
 * Imports contacts or companies from a CSV file with a column → field mapping.
 */
class CsvImporter
{
    /**
     * Fields stored in their own columns (everything else from the blueprint goes into `data`).
     */
    private const COLUMNS = [
        'contacts' => ['first_name', 'last_name', 'email', 'phone', 'status'],
        'companies' => ['name', 'email', 'phone', 'website', 'status'],
    ];

    /**
     * Blueprint fields a column can map to.
     *
     * @return array<string, string> handle => label
     */
    public static function targets(string $type): array
    {
        $blueprint = $type === 'companies' ? Company::blueprint() : Contact::blueprint();

        return collect($blueprint->fields()->all())
            ->reject(fn ($field) => in_array($field->type(), ['section', 'assets', 'users', 'grid', 'replicator', 'bard', 'spacer'], true))
            ->map(fn ($field) => $field->display())
            ->all();
    }

    /**
     * @return array{headers: array<int, string>, rows: array<int, array<int, string>>, total: int}
     */
    public static function preview(string $path, int $rows = 5): array
    {
        $file = self::open($path);
        $headers = array_map(fn ($header) => trim((string) $header, " \t\n\r\0\x0B\u{FEFF}"), (array) $file->fgetcsv());
        $sample = [];
        $total = 0;

        while (! $file->eof()) {
            $row = $file->fgetcsv();
            if (! $row || $row === [null]) {
                continue;
            }
            $total++;
            if (count($sample) < $rows) {
                $sample[] = array_map('strval', $row);
            }
        }

        return ['headers' => $headers, 'rows' => $sample, 'total' => $total];
    }

    /**
     * Guess a target field for each header.
     *
     * @param  array<int, string>  $headers
     * @return array<int, ?string>
     */
    public static function guess(string $type, array $headers): array
    {
        $targets = self::targets($type);
        $byLabel = collect($targets)->mapWithKeys(fn ($label, $handle) => [Str::snake(Str::lower($label)) => $handle]);
        $aliases = [
            'email_address' => 'email', 'e_mail' => 'email', 'firstname' => 'first_name', 'surname' => 'last_name', 'lastname' => 'last_name',
            'telephone' => 'phone', 'mobile' => 'phone', 'company_name' => 'company', 'organisation' => 'company', 'organization' => 'company',
            'url' => 'website', 'zip' => 'postcode', 'postal_code' => 'postcode', 'state' => 'region', 'address' => 'address_line_1',
        ];

        if ($type === 'companies') {
            $aliases = ['company_name' => 'name', 'company' => 'name', 'organisation' => 'name', 'organization' => 'name', 'url' => 'website', 'email_address' => 'email', 'telephone' => 'phone'];
        }

        $used = [];

        return array_map(function ($header) use ($targets, $byLabel, $aliases, &$used) {
            $key = Str::snake(Str::lower(preg_replace('/[^A-Za-z0-9]+/', ' ', $header)));
            $handle = isset($targets[$key]) ? $key : ($byLabel[$key] ?? $aliases[$key] ?? null);

            if (! $handle || ! isset($targets[$handle]) || in_array($handle, $used, true)) {
                return null;
            }

            return $used[] = $handle;
        }, $headers);
    }

    /**
     * @param  array<int, ?string>  $mapping  column index => field handle
     * @param  array{update?: bool, tags?: array<int, string>, status?: ?string}  $options
     * @return array{created: int, updated: int, skipped: int, errors: array<int, string>}
     */
    public static function import(string $type, string $path, array $mapping, array $options = []): array
    {
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
        $file = self::open($path);
        $file->fgetcsv();
        $line = 1;

        while (! $file->eof()) {
            $row = $file->fgetcsv();
            $line++;

            if (! $row || $row === [null]) {
                continue;
            }

            $values = [];
            foreach ($mapping as $index => $handle) {
                $value = trim((string) ($row[$index] ?? ''));
                if ($handle && $value !== '') {
                    $values[$handle] = $value;
                }
            }

            try {
                $outcome = $type === 'companies' ? self::importCompany($values, $options) : self::importContact($values, $options);
                $result[$outcome]++;
            } catch (\InvalidArgumentException $e) {
                $result['skipped']++;
                if (count($result['errors']) < 50) {
                    $result['errors'][] = __('Row :line: :error', ['line' => $line, 'error' => $e->getMessage()]);
                }
            }
        }

        return $result;
    }

    /**
     * @param  array<string, string>  $values
     * @param  array<string, mixed>  $options
     */
    private static function importContact(array $values, array $options): string
    {
        if (isset($values['email'])) {
            $values['email'] = mb_strtolower($values['email']);
            if (! filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException(__('“:email” isn’t a valid email address', ['email' => $values['email']]));
            }
        }

        if (empty($values['email']) && empty($values['first_name']) && empty($values['last_name'])) {
            throw new \InvalidArgumentException(__('needs an email or a name'));
        }

        $contact = isset($values['email']) ? Contact::findByEmail($values['email']) : null;

        if ($contact && empty($options['update'])) {
            return 'skipped';
        }

        $outcome = $contact ? 'updated' : 'created';
        $contact ??= new Contact(['status' => $options['status'] ?? null ?: 'lead']);

        self::fill($contact, 'contacts', $values);

        if (isset($values['company'])) {
            $contact->company_id = Company::firstOrCreate(['name' => Str::limit($values['company'], 250, '')])->id;
        }

        $contact->save();

        if (isset($values['aliases'])) {
            $existing = $contact->aliases()->pluck('email')->all();
            foreach (self::split($values['aliases']) as $alias) {
                $alias = mb_strtolower($alias);
                if (filter_var($alias, FILTER_VALIDATE_EMAIL) && $alias !== $contact->email && ! in_array($alias, $existing, true) && ! Contact::findByEmail($alias)) {
                    $contact->aliases()->create(['email' => $alias]);
                }
            }
        }

        self::tag($contact, $values, $options);

        return $outcome;
    }

    /**
     * @param  array<string, string>  $values
     * @param  array<string, mixed>  $options
     */
    private static function importCompany(array $values, array $options): string
    {
        if (empty($values['name'])) {
            throw new \InvalidArgumentException(__('needs a company name'));
        }

        $company = Company::whereRaw('lower(name) = ?', [mb_strtolower($values['name'])])->first();

        if ($company && empty($options['update'])) {
            return 'skipped';
        }

        $outcome = $company ? 'updated' : 'created';
        $company ??= new Company(['status' => $options['status'] ?? null ?: 'lead']);

        self::fill($company, 'companies', $values);
        $company->save();
        self::tag($company, $values, $options);

        return $outcome;
    }

    /**
     * @param  array<string, string>  $values
     */
    private static function fill(Contact|Company $record, string $type, array $values): void
    {
        $data = (array) $record->data;

        foreach ($values as $handle => $value) {
            if (in_array($handle, ['company', 'tags', 'aliases', 'owner'], true)) {
                continue;
            }

            if (in_array($handle, self::COLUMNS[$type], true)) {
                $record->{$handle} = Str::limit($value, 250, '');
            } else {
                $data[$handle] = $handle === 'country' ? strtoupper($value) : $value;
            }
        }

        $record->data = $data ?: null;
    }

    /**
     * @param  array<string, string>  $values
     * @param  array<string, mixed>  $options
     */
    private static function tag(Contact|Company $record, array $values, array $options): void
    {
        $tags = array_merge(self::split($values['tags'] ?? ''), (array) ($options['tags'] ?? []));

        if ($tags) {
            $record->attachTags($tags);
        }
    }

    /**
     * @return array<int, string>
     */
    private static function split(string $value): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[,;|]/', $value))));
    }

    private static function open(string $path): SplFileObject
    {
        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD);

        // Sniff the delimiter (comma, semicolon or tab) from the header line.
        $first = (string) (new SplFileObject($path))->fgets();
        $delimiter = collect([',', ';', "\t"])->sortByDesc(fn ($d) => substr_count($first, $d))->first();
        $file->setCsvControl($delimiter, '"', '');

        return $file;
    }
}
