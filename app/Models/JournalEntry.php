<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['team_id', 'journal_template_id', 'posted_by', 'request_id', 'request_hash', 'template_name', 'date', 'description', 'approved_by', 'approved_at', 'reversal_of_id', 'reversal_reason'])]
class JournalEntry extends Model
{
    public const UPDATED_AT = null;

    /** @return HasMany<JournalEntryLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class)->orderBy('id');
    }
}
