<?php

namespace App\Http\Controllers;

use App\Actions\Opening\StoreOpeningBalances;
use App\Enums\TradeDirection;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class OpeningBalancesController extends Controller
{
    public function create(Team $current_team): Response|RedirectResponse
    {
        $existing = DB::table('opening_balances')->where('team_id', $current_team->id)->value('id');
        if ($existing !== null) {
            return to_route('opening-balances.show', [$current_team, $existing]);
        }
        $firstDate = DB::table('journal_entries')->where('team_id', $current_team->id)->min('date');
        $maxDate = $firstDate === null ? '9999-12-30' : Carbon::parse($firstDate)->subDay()->toDateString();

        return Inertia::render('opening/Create', [
            'accounts' => DB::table('journal_accounts')->where('team_id', $current_team->id)->where('active', true)
                ->whereIn('type', ['asset', 'liability', 'equity'])
                ->where(fn ($query) => $query->whereNull('trade_role')->orWhereNotIn('trade_role', ['receivable', 'payable', 'inventory']))
                ->orderBy('code')->get(['id', 'code', 'name', 'type']),
            'date' => min(now('Asia/Jakarta')->subDay()->toDateString(), $maxDate),
            'maxDate' => $maxDate,
            'storeUrl' => route('opening-balances.store', $current_team),
            'indexUrl' => route('chart-of-accounts.index', $current_team),
            'requestId' => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request, Team $current_team, StoreOpeningBalances $opening): RedirectResponse
    {
        $rules = [
            'request_id' => ['required', 'uuid'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:9999-12-30'],
            'description' => ['nullable', 'string', 'max:500'],
            'lines' => ['nullable', 'array', 'list', 'max:50'],
            'lines.*' => ['array:account_id,debit,credit'],
            'lines.*.account_id' => ['required', 'integer', 'min:1', 'distinct'],
            'lines.*.debit' => ['nullable', 'integer', 'between:0,'.StoreOpeningBalances::MAX_AMOUNT],
            'lines.*.credit' => ['nullable', 'integer', 'between:0,'.StoreOpeningBalances::MAX_AMOUNT],
            'inventory' => ['nullable', 'array', 'list', 'max:100'],
            'inventory.*' => ['array:sku,name,quantity,unit_cost'],
            'inventory.*.sku' => ['required', 'string', 'max:80'],
            'inventory.*.name' => ['required', 'string', 'max:160'],
            'inventory.*.quantity' => ['required', 'integer', 'between:1,1000000'],
            'inventory.*.unit_cost' => ['required', 'integer', 'between:1,999999999999'],
        ];
        foreach (['receivables', 'payables'] as $category) {
            $rules[$category] = ['nullable', 'array', 'list', 'max:100'];
            $rules[$category.'.*'] = ['array:partner,reference,invoice_date,due_date,amount'];
            $rules[$category.'.*.partner'] = ['required', 'string', 'max:160'];
            $rules[$category.'.*.reference'] = ['required', 'string', 'max:100'];
            $rules[$category.'.*.invoice_date'] = ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:date'];
            $rules[$category.'.*.due_date'] = ['nullable', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:9999-12-31'];
            $rules[$category.'.*.amount'] = ['required', 'integer', 'between:1,'.StoreOpeningBalances::MAX_AMOUNT];
        }
        $data = $request->validate($rules);
        $id = $opening->handle($current_team, $request->user()->id, $data);

        return to_route('opening-balances.show', [$current_team, $id]);
    }

    public function show(Team $current_team, int $opening): Response
    {
        $batch = DB::table('opening_balances')->where('team_id', $current_team->id)->where('id', $opening)->first();
        abort_if($batch === null, 404);
        $documents = DB::table('trade_documents')->where('team_id', $current_team->id)->where('opening_balance_id', $opening)->orderBy('id')->get();
        $details = $documents->map(fn ($document) => [
            'id' => $document->id, 'partner' => $document->partner, 'reference' => $document->reference,
            'invoice_date' => $document->date, 'due_date' => $document->due_date, 'amount' => (int) $document->total,
            'direction' => $document->direction,
            'documentUrl' => route('trade.show', [$current_team, TradeDirection::from($document->direction), $document->id]),
        ]);

        return Inertia::render('opening/Show', [
            'batch' => [
                'id' => $batch->id, 'date' => $batch->date, 'description' => $batch->description,
                'journalId' => $batch->journal_entry_id,
                'journalUrl' => route('journals.show', [$current_team, $batch->journal_entry_id]),
                'postedBy' => $batch->created_by === null ? null : DB::table('users')->where('id', $batch->created_by)->value('name'),
                'createdAt' => $batch->created_at,
            ],
            'lines' => DB::table('journal_entry_lines')->where('journal_entry_id', $batch->journal_entry_id)
                ->orderBy('id')->get(['id', 'account_code', 'account_name', 'side', 'amount']),
            'receivables' => $details->where('direction', TradeDirection::Sales->value)->values(),
            'payables' => $details->where('direction', TradeDirection::Purchases->value)->values(),
            'inventory' => DB::table('opening_inventory')->where('opening_balance_id', $opening)->orderBy('id')->get(),
            'indexUrl' => route('journals.index', $current_team),
        ]);
    }
}
