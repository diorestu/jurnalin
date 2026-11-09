<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['journal_account_id', 'account_code', 'account_name', 'side', 'amount'])]
class JournalEntryLine extends Model
{
    public $timestamps = false;
}
