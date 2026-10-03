<?php

namespace RadThemes\AlpCrm\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RadThemes\AlpCrm\Models\Contact;
use RadThemes\AlpCrm\Support\Presenter;

/**
 * @mixin Contact
 */
class ContactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name(),
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status,
            'status_label' => Presenter::optionLabel(Contact::blueprint(), 'status', $this->status),
            'company' => $this->company?->name,
            'tags' => $this->tags->pluck('name'),
            'created_at' => $this->created_at?->toIso8601String(),
            'last_contacted_at' => $this->last_contacted_at?->toIso8601String(),
            'show_url' => cp_route('alp-crm.contacts.show', $this->resource),
            'edit_url' => cp_route('alp-crm.contacts.edit', $this->resource),
        ];
    }
}
