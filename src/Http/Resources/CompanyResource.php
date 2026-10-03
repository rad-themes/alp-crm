<?php

namespace RadThemes\AlpCrm\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RadThemes\AlpCrm\Models\Company;
use RadThemes\AlpCrm\Support\Presenter;

/**
 * @mixin Company
 */
class CompanyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'website' => $this->website,
            'status' => $this->status,
            'status_label' => Presenter::optionLabel(Company::blueprint(), 'status', $this->status),
            'contacts_count' => $this->contacts_count,
            'tags' => $this->tags->pluck('name'),
            'created_at' => $this->created_at?->toIso8601String(),
            'show_url' => cp_route('alp-crm.companies.show', $this->resource),
            'edit_url' => cp_route('alp-crm.companies.edit', $this->resource),
        ];
    }
}
