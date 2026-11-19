<?php

namespace App\Http\Controllers;

use App\Actions\Accounting\JournalDrafts;
use App\Actions\Trade\PostTradeJournal;
use App\Actions\Trade\StoreTradeDocument;
use App\Actions\Trade\TradeBalances;
use App\Actions\Trade\TradeNotes;
use App\Enums\TradeDirection;
use App\Enums\TradeKind;
use App\Models\Team;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use stdClass;

class TradeController extends Controller
{
    public function __construct(private TradeBalances $balances, private PostTradeJournal $journal) {}

    public function index(Request $request, Team $current_team, TradeDirection $direction): Response
    {
        $term = $request->query('search');
        $search = is_string($term) ? mb_substr(trim($term), 0, 100) : '';
        $kinds = TradeKind::available($direction);
        $filter = $request->query('kind');
        $kind = is_string($filter) ? TradeKind::tryFrom($filter) : null;
        $kind = in_array($kind, $kinds, true) ? $kind : null;

        $query = $this->documents($current_team, $direction);
        if ($kind !== null) {
            $query->where('kind', $kind->value);
        }
        if ($search !== '') {
            $query->where(function (Builder $query) use ($search, $kinds, $direction) {
                $pattern = '%'.mb_strtolower($search).'%';
                $query->whereRaw('LOWER(partner) LIKE ?', [$pattern])->orWhereRaw('LOWER(reference) LIKE ?', [$pattern]);

                if (preg_match('/\A([A-Z]+)-(\d{1,18})\z/i', $search, $match)) {
                    $id = (int) $match[2];
                    foreach ($kinds as $kind) {
                        if (str_starts_with($kind->number($direction, $id), strtoupper($match[1]).'-')) {
                            $query->orWhere(fn (Builder $query) => $query->where('kind', $kind->value)->where('id', $id));
                        }
                    }
                }
            });
        }

        $page = $query->orderByDesc('date')->orderByDesc('id')->paginate(20)->withQueryString();
        $balances = $this->balances->invoices($page->getCollection());
        $page->through(fn (stdClass $document) => $this->summary($current_team, $direction, $document, $balances[$document->id] ?? null));

        return Inertia::render('trade/Index', [
            ...$this->pageProps($current_team, $direction),
            'documents' => $page,
            'kinds' => $this->kindOptions($direction, $kinds),
            'filters' => ['kind' => $kind->value ?? '', 'search' => $search],
            'createUrl' => route('trade.create', [$current_team, $direction]),
        ]);
    }

    public function create(Request $request, Team $current_team, TradeDirection $direction): Response
    {
        $editing = null;
        $draftId = $request->query('draft');
        if ($draftId !== null) {
            abort_unless(is_string($draftId) && ctype_digit($draftId), 404);
            $workflow = app(JournalDrafts::class);
            $editing = $workflow->find($current_team, (int) $draftId);
            $workflow->assertEdit($current_team, $request->user(), $editing);
            abort_unless($editing->mode === 'trade', 404);
            $draftPayload = $workflow->payload($editing);
            abort_unless($draftPayload['direction'] === $direction->value, 404);
            $request->merge(['kind' => $draftPayload['data']['kind']]);
        }
        $sourceId = $editing === null ? $request->query('source') : ($draftPayload['data']['source_id'] ?? null);
        $source = null;
        $sourceProps = null;
        $kind = $request->input('kind');
        $selected = is_string($kind) ? TradeKind::tryFrom($kind) : null;
        $kinds = TradeKind::standalone($direction);
        if ($sourceId !== null) {
            abort_unless((is_string($sourceId) && ctype_digit($sourceId)) || is_int($sourceId), 404);
            $source = $this->find($current_team, $direction, (int) $sourceId);
            abort_unless($selected !== null && in_array($selected, TradeKind::from($source->kind)->next($direction), true), 404);

            $kinds = [$selected];
            $sourceProps = [
                'id' => (int) $source->id,
                'number' => TradeKind::from($source->kind)->number($direction, (int) $source->id),
                'partner' => $source->partner,
                'partner_id' => $source->partner_id,
                'date' => $source->date,
                'lines' => $selected->isCash() ? [] : $this->balances->sourceLines((int) $source->id, $selected),
                'availableAmount' => $selected->isCash() ? $this->balances->limit($source, $selected) : null,
            ];
        } elseif (! in_array($selected, $kinds, true)) {
            $selected = $kinds[0];
        }

        return Inertia::render('trade/Create', [
            ...$this->pageProps($current_team, $direction),
            'kinds' => $this->kindOptions($direction, $kinds),
            'selectedKind' => $selected->value,
            'catalog' => DB::table('catalog_items')->where('team_id', $current_team->id)->orderBy('name')->get(['id', 'sku', 'name', 'unit', 'buy_price', 'sell_price']),
            'partners' => DB::table('trade_partners')->where('team_id', $current_team->id)->where('direction', $direction->value)->orderBy('name')->get(['id', 'name', 'code', 'terms']),
            'source' => $sourceProps,
            'cashAccounts' => $this->journal->cashAccounts($current_team->id),
            'purchaseAccounts' => $direction === TradeDirection::Purchases
                ? $this->journal->purchaseAccounts($current_team->id)->prepend((object) [
                    'id' => 0, 'code' => 'Otomatis', 'name' => 'Persediaan Barang',
                ])
                : [],
            'date' => max(now('Asia/Jakarta')->toDateString(), $source->date ?? ''),
            'canPost' => app(JournalDrafts::class)->supervisor($request->user(), $current_team),
            'editingDraft' => $editing === null ? null : ['id' => $editing->id, 'version' => $editing->version, 'data' => $draftPayload['data']],
            'requestId' => $editing->request_id ?? (string) Str::uuid(),
            'storeUrl' => route('trade.store', [$current_team, $direction]),
            'formUrl' => route('trade.create', [$current_team, $direction,
                ...($source === null ? [] : ['source' => $source->id, 'kind' => $selected->value]),
            ]),
            'backUrl' => $source === null ? route('trade.index', [$current_team, $direction])
                : route('trade.show', [$current_team, $direction, $source->id]),
        ]);
    }

    public function show(Team $current_team, TradeDirection $direction, int $document): Response
    {
        $record = $this->find($current_team, $direction, $document);
        $kind = TradeKind::from($record->kind);
        $source = $record->source_id === null ? null : $this->find($current_team, $direction, (int) $record->source_id);
        $opening = $record->opening_balance_id === null ? null : DB::table('opening_balances')
            ->where('team_id', $current_team->id)->where('id', $record->opening_balance_id)->first();
        $journalId = $record->journal_entry_id ?? $opening->journal_entry_id ?? null;
        $related = $this->documents($current_team, $direction)->where('source_id', $document)
            ->orderByDesc('date')->orderByDesc('id')->get();
        $balances = $this->balances->invoices($related->concat([$record]));
        $actions = [];
        foreach ($kind->next($direction) as $next) {
            $possible = $next->isCash()
                ? $this->balances->limit($record, $next) > 0
                : $this->balances->sourceLines($document, $next)->contains(fn (stdClass $line) => $line->remaining > 0);
            if ($possible) {
                $actions[] = [
                    'label' => $next->label($direction),
                    'url' => route('trade.create', [$current_team, $direction, 'source' => $document, 'kind' => $next->value]),
                ];
            }
        }

        return Inertia::render('trade/Show', [
            'pdfUrl' => route('trade.pdf', [$current_team, $direction, $document]),
            'uploadUrl' => route('trade.attachments.upload', [$current_team, $direction, $document]),
            'attachments' => DB::table('trade_attachments as a')->join('users as u', 'u.id', '=', 'a.uploaded_by')->where('a.document_id', $document)->orderByDesc('a.id')->get(['a.id', 'a.name', 'a.size', 'a.created_at', 'u.name as uploader'])->map(fn ($a) => [...(array) $a, 'url' => route('trade.attachments.download', [$current_team, $direction, $document, $a->id])]),
            ...$this->pageProps($current_team, $direction),
            'document' => [
                ...$this->summary($current_team, $direction, $record, $balances[$document] ?? null),
                'due_date' => $record->due_date,
                'reference' => $record->reference,
                'notes' => $record->notes,
                'notesRuns' => $record->notes_delta === null ? [] : json_decode($record->notes_delta, true, 32, JSON_THROW_ON_ERROR),
                'appliedAdvance' => $kind === TradeKind::Invoice ? $this->balances->applied($document) : 0,
                'availableAmount' => $kind === TradeKind::Advance ? $this->balances->availableAdvance($record) : null,
                'source' => $source !== null ? [
                    'number' => TradeKind::from($source->kind)->number($direction, (int) $source->id),
                    'url' => route('trade.show', [$current_team, $direction, $source->id]),
                ] : ($opening === null ? null : [
                    'number' => 'Saldo Awal #'.$opening->id,
                    'url' => route('opening-balances.show', [$current_team, $opening->id]),
                ]),
                'lines' => DB::table('trade_document_lines')->where('document_id', $document)->orderBy('id')
                    ->get(['id', 'name', 'quantity', 'unit_price', 'total', 'unit_discount', 'tax_code', 'tax_bps', 'tax_amount', 'tax_included']),
                'journalId' => $journalId === null ? null : (int) $journalId,
                'journalUrl' => $journalId === null ? null : route('journals.show', [$current_team, $journalId]),
            ],
            'actions' => $actions,
            'related' => $related->map(fn (stdClass $row) => $this->summary($current_team, $direction, $row, $balances[$row->id] ?? null)),
        ]);
    }

    public function store(Request $request, Team $current_team, TradeDirection $direction, StoreTradeDocument $store, TradeNotes $notes): RedirectResponse
    {
        $data = $request->validate([
            'request_id' => ['required', 'uuid'],
            'kind' => ['required', Rule::enum(TradeKind::class)],
            'action' => ['nullable', 'in:post,draft,submit'],
            'draft_id' => ['nullable', 'integer', 'min:1'],
            'version' => ['required_with:draft_id', 'nullable', 'integer', 'min:1'],
            'source_id' => ['nullable', 'integer', 'min:1'],
            'partner' => ['nullable', 'string', 'max:160'],
            'partner_id' => ['nullable', 'integer', Rule::exists('trade_partners', 'id')->where('team_id', $current_team->id)->where('direction', $direction->value)],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:9999-12-31'],
            'due_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date', 'before_or_equal:9999-12-31'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:'.TradeNotes::MAX_LENGTH],
            'notes_delta' => ['nullable', 'string', 'json', 'max:40000'],
            'lines' => ['nullable', 'array', 'list', 'max:50'],
            'lines.*' => ['array:name,quantity,unit_price,source_line_id,item_id,unit_discount,tax_code,tax_bps,tax_included'],
            'lines.*.unit_discount' => ['nullable', 'integer', 'between:0,999999999999'],
            'lines.*.tax_code' => ['nullable', 'string', 'max:30'],
            'lines.*.tax_bps' => ['nullable', 'integer', 'between:0,10000'],
            'lines.*.tax_included' => ['nullable', 'boolean'],
            'lines.*.item_id' => ['nullable', 'integer', Rule::exists('catalog_items', 'id')->where('team_id', $current_team->id)],
            'lines.*.source_line_id' => ['nullable', 'integer', 'min:1'],
            'lines.*.name' => ['nullable', 'string', 'max:160'],
            'lines.*.quantity' => ['required', 'integer', 'between:1,1000000'],
            'lines.*.unit_price' => ['nullable', 'integer', 'between:1,999999999999'],
            'amount' => ['nullable', 'integer', 'between:1,'.StoreTradeDocument::MAX_AMOUNT],
            'account_id' => ['nullable', 'integer', 'min:0'],
        ]);

        if (isset($data['notes_delta'])) {
            $data = [...$data, ...$notes->normalize($data['notes_delta'])];
        } else {
            unset($data['notes_delta']);
        }

        $workflow = app(JournalDrafts::class);
        $kind = TradeKind::from($data['kind']);
        $financial = $kind->isCash() || in_array($kind, [TradeKind::Invoice, TradeKind::Return], true);
        $canPost = $workflow->supervisor($request->user(), $current_team);
        $draftId = isset($data['draft_id']) ? (int) $data['draft_id'] : null;
        $version = isset($data['version']) ? (int) $data['version'] : null;
        $action = $data['action'] ?? ($financial && ! $canPost ? 'submit' : 'post');
        unset($data['action'], $data['draft_id'], $data['version']);
        if ($financial && ($action !== 'post' || $draftId !== null)) {
            if ($action === 'post') {
                throw ValidationException::withMessages(['action' => 'Ajukan dokumen draf untuk persetujuan.']);
            }
            $id = $workflow->saveTrade($current_team, $request->user(), $direction, $data, $action, $draftId, $version);

            return to_route('journals.draft-show', [$current_team, $id]);
        }
        if ($financial) {
            abort_unless($canPost, 403);
        }
        if (DB::table('journal_drafts')->where('team_id', $current_team->id)->where('request_id', $data['request_id'])->exists()) {
            throw ValidationException::withMessages(['request_id' => 'Lanjutkan transaksi melalui draf yang tersimpan.']);
        }

        $id = $store->handle($current_team, $request->user()->id, $direction, $data);

        return to_route('trade.show', [$current_team, $direction, $id]);
    }

    private function documents(Team $team, TradeDirection $direction): Builder
    {
        return DB::table('trade_documents')->where('team_id', $team->id)->where('direction', $direction->value);
    }

    private function find(Team $team, TradeDirection $direction, int $id): stdClass
    {
        $document = $this->documents($team, $direction)->where('id', $id)->first();
        abort_if($document === null, 404);

        return $document;
    }

    /** @return array{direction: string, title: string, indexUrl: string} */
    private function pageProps(Team $team, TradeDirection $direction): array
    {
        return [
            'direction' => $direction->value,
            'title' => $direction->label(),
            'indexUrl' => route('trade.index', [$team, $direction]),
        ];
    }

    /**
     * @param  list<TradeKind>  $kinds
     * @return list<array{value: string, label: string}>
     */
    private function kindOptions(TradeDirection $direction, array $kinds): array
    {
        return array_map(fn (TradeKind $kind) => ['value' => $kind->value, 'label' => $kind->label($direction)], $kinds);
    }

    /** @return array{id: int, number: string, kind: string, kindLabel: string, date: string, partner: string, total: int, openAmount: int|null, url: string} */
    private function summary(Team $team, TradeDirection $direction, stdClass $document, ?int $balance): array
    {
        $kind = TradeKind::from($document->kind);

        return [
            'id' => (int) $document->id,
            'number' => $kind->number($direction, (int) $document->id),
            'kind' => $kind->value,
            'kindLabel' => $document->opening_balance_id === null ? $kind->label($direction)
                : ($direction === TradeDirection::Sales ? 'Piutang Awal' : 'Utang Awal'),
            'date' => $document->date,
            'partner' => $document->partner,
            'total' => (int) $document->total,
            'openAmount' => $balance,
            'url' => route('trade.show', [$team, $direction, $document->id]),
        ];
    }
}
