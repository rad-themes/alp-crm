<?php

namespace RadThemes\RadpackCrm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A private file attached to a contact or company, optionally shared in the client portal.
 *
 * @property int $id
 * @property string $name
 * @property string $disk
 * @property string $path
 * @property bool $portal
 */
class File extends Model
{
    protected $table = 'crm_files';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['portal' => 'boolean', 'size' => 'integer'];
    }

    protected static function booted(): void
    {
        static::deleted(function (File $file) {
            Storage::disk($file->disk)->delete($file->path);
        });
    }

    public static function store(UploadedFile $upload, Contact|Company $owner, ?string $userId = null): self
    {
        $disk = config('radpack-crm.files_disk') ?: 'local';
        $folder = 'radpack-crm/files/'.($owner instanceof Contact ? 'contacts' : 'companies').'/'.$owner->id;
        $path = $upload->storeAs($folder, Str::random(16).'.'.strtolower($upload->getClientOriginalExtension() ?: 'bin'), ['disk' => $disk]);

        return static::create([
            $owner instanceof Contact ? 'contact_id' : 'company_id' => $owner->id,
            'name' => Str::limit(basename($upload->getClientOriginalName()), 250, ''),
            'disk' => $disk,
            'path' => $path,
            'size' => $upload->getSize(),
            'mime' => $upload->getMimeType(),
            'user_id' => $userId,
        ]);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function download(): StreamedResponse
    {
        return Storage::disk($this->disk)->download($this->path, $this->name, [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function humanSize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $this->size;
        $i = 0;

        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return round($size, $i ? 1 : 0).' '.$units[$i];
    }
}
