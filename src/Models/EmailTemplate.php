<?php

namespace RadThemes\AlpCrm\Models;

use Illuminate\Database\Eloquent\Model;
use RadThemes\AlpCrm\Models\Concerns\HasBlueprint;

/**
 * A reusable email ("canned reply") with merge tags like {{ first_name }}.
 *
 * @property int $id
 * @property string $name
 * @property string $subject
 * @property string $body
 */
class EmailTemplate extends Model
{
    use HasBlueprint;

    protected $table = 'crm_email_templates';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    public static function blueprintHandle(): string
    {
        return 'email_template';
    }

    protected function blueprintColumns(): array
    {
        return ['name', 'subject', 'body'];
    }
}
