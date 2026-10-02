<?php

namespace RadThemes\RadpackCrm\Support;

use RadThemes\ClientPortal\Extensions;
use RadThemes\RadpackCrm\Models\Company;
use RadThemes\RadpackCrm\Models\Contact;
use RadThemes\RadpackCrm\Models\File;
use RadThemes\RadpackCrm\Models\Password;
use Statamic\Facades\User;

/**
 * Files and saved passwords on a contact or company profile.
 */
class Attachments
{
    /**
     * @return array<string, mixed>
     */
    public static function for(Contact|Company $owner): array
    {
        $column = $owner instanceof Contact ? 'contact_id' : 'company_id';
        $type = $owner instanceof Contact ? 'contact' : 'company';
        $canSeePasswords = User::current()?->can('manage crm passwords') ?? false;

        return [
            'files' => File::where($column, $owner->id)->latest('id')->get()->map(fn (File $file) => [
                'id' => $file->id,
                'name' => $file->name,
                'size' => $file->humanSize(),
                'portal' => $file->portal,
                'created_at' => $file->created_at?->toIso8601String(),
                'author' => $file->user_id ? User::find($file->user_id)?->name() : null,
                'download_url' => cp_route('radpack-crm.files.download', $file),
                'update_url' => cp_route('radpack-crm.files.update', $file),
                'destroy_url' => cp_route('radpack-crm.files.destroy', $file),
            ]),
            'passwords' => $canSeePasswords ? Password::where($column, $owner->id)->orderBy('label')->get()->map(fn (Password $password) => [
                'id' => $password->id,
                'label' => $password->label,
                'url' => $password->url,
                'username' => $password->username,
                'has_notes' => filled($password->notes),
                'reveal_url' => cp_route('radpack-crm.passwords.reveal', $password),
                'update_url' => cp_route('radpack-crm.passwords.update', $password),
                'destroy_url' => cp_route('radpack-crm.passwords.destroy', $password),
            ]) : null,
            'attachmentUrls' => [
                'files' => cp_route('radpack-crm.files.store', [$type, $owner->id]),
                'passwords' => cp_route('radpack-crm.passwords.store', [$type, $owner->id]),
            ],
            'portalInstalled' => class_exists(Extensions::class),
        ];
    }
}
