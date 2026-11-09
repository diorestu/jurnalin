<?php

namespace App\Http\Controllers;

use App\Actions\Accounting\ReverseJournal;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class JournalReversalController extends Controller
{
    public function __invoke(Request $request, Team $current_team, int $journal, ReverseJournal $reverse): RedirectResponse
    {
        $data = $request->validate([
            'request_id' => ['required', 'uuid'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:9999-12-31'],
            'reason' => ['required', 'string', 'min:3', 'max:400'],
        ]);
        $id = $reverse->handle($current_team, $request->user(), $journal, $data);

        return to_route('journals.show', [$current_team, $id]);
    }
}
