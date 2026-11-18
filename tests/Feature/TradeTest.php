<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function saveTrade($test, User $user, string $direction, array $data): int
{
    $id = (string) Str::uuid();
    $test->actingAs($user)->post(route('trade.store', [$user->currentTeam, $direction]), [
        'date' => '2026-11-07',
        'request_id' => $id,
        ...$data,
    ])->assertSessionHasNoErrors()->assertRedirect();

    return (int) DB::table('trade_documents')->where('request_id', $id)->value('id');
}

function sourceTradeLines(int $documentId, int $quantity): array
{
    return [[
        'source_line_id' => DB::table('trade_document_lines')->where('document_id', $documentId)->value('id'),
        'quantity' => $quantity,
    ]];
}

it('carries weighted shipment cost into partial invoices and restores original cost on return', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $this->actingAs($user)->post(route('catalog.save', ['current_team' => $team]), ['sku' => 'A', 'name' => 'Barang A', 'unit' => 'pcs', 'stock' => true, 'buy_price' => 100, 'sell_price' => 400])->assertRedirect()->assertSessionHasNoErrors();
    $item = DB::table('catalog_items')->value('id');
    $line = fn ($q, $price) => [['item_id' => $item, 'name' => 'Barang A', 'quantity' => $q, 'unit_price' => $price]];
    foreach ([100, 200] as $price) {
        saveTrade($this, $user, 'pembelian', ['kind' => 'invoice', 'partner' => 'Vendor', 'lines' => $line(2, $price)]);
    }
    $order = saveTrade($this, $user, 'penjualan', ['kind' => 'order', 'partner' => 'Pembeli', 'lines' => $line(4, 400)]);
    $delivery = saveTrade($this, $user, 'penjualan', ['kind' => 'delivery', 'source_id' => $order, 'lines' => sourceTradeLines($order, 4)]);
    $invoice = saveTrade($this, $user, 'penjualan', ['kind' => 'invoice', 'source_id' => $delivery, 'lines' => sourceTradeLines($delivery, 2)]);
    expect((int) DB::table('stock_movements')->sum('quantity'))->toBe(0);
    $journal = DB::table('trade_documents')->where('id', $invoice)->value('journal_entry_id');
    $this->assertDatabaseHas('journal_entry_lines', ['journal_entry_id' => $journal, 'account_code' => '5101', 'side' => 'debit', 'amount' => 300]);
    saveTrade($this, $user, 'penjualan', ['kind' => 'return', 'source_id' => $invoice, 'lines' => sourceTradeLines($invoice, 1)]);
    expect((int) DB::table('stock_movements')->sum('quantity'))->toBe(1)
        ->and((int) DB::table('stock_movements')->sum('value'))->toBe(150);
    $this->post(route('trade.store', [$team, 'penjualan']), ['request_id' => (string) Str::uuid(), 'date' => '2026-11-08', 'kind' => 'invoice', 'partner' => 'Pembeli', 'lines' => $line(2, 400)])->assertSessionHasErrors('lines');
    expect((int) DB::table('stock_movements')->sum('quantity'))->toBe(1);
});

it('reconciles historical aging after a later payment and keeps the invoice contact snapshot', function (string $direction) {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $this->actingAs($user)->post(route('partners.save', ['current_team' => $team]), ['direction' => $direction, 'code' => 'ACME', 'name' => 'Acme', 'terms' => 10])->assertRedirect()->assertSessionHasNoErrors();
    $id = DB::table('trade_partners')->where('team_id', $team->id)->value('id');
    $invoice = saveTrade($this, $user, $direction, ['date' => '2026-10-01', 'kind' => 'invoice', 'partner' => 'Acme', 'partner_id' => $id, 'lines' => [['name' => 'Jasa', 'quantity' => 1, 'unit_price' => 150000]]]);
    $this->assertDatabaseHas('trade_documents', ['id' => $invoice, 'partner_id' => $id, 'due_date' => '2026-10-11']);
    $this->post(route('partners.save', [$team, $id]), ['code' => 'ACME', 'name' => 'Acme Baru'])->assertSessionHasNoErrors();
    $cash = DB::table('journal_accounts')->where('team_id', $team->id)->where('code', '1101')->value('id');
    saveTrade($this, $user, $direction, ['kind' => 'payment', 'source_id' => $invoice, 'date' => '2026-11-01', 'account_id' => $cash, 'amount' => 150000]);
    $this->get(route('reports.aging', [$team, 'direction' => $direction, 'as_of' => '2026-10-31']))->assertInertia(fn (Assert $p) => $p->component('Aging')->where('net', '150000')->where('difference', '0')->where('rows.0.bucket', '1–30 hari')->where('rows.0.partner', 'Acme Baru'));
    $this->get(route('reports.aging', [$team, 'direction' => $direction, 'as_of' => '2026-11-01']))->assertInertia(fn (Assert $p) => $p->where('net', '0')->has('rows', 0));
    $this->assertDatabaseHas('trade_documents', ['id' => $invoice, 'partner' => 'Acme']);
    $other = User::factory()->create();
    $this->actingAs($other)->get(route('reports.aging', [$other->currentTeam, 'direction' => $direction, 'contact' => $id]))->assertNotFound();
})->with(['penjualan', 'pembelian']);

it('processes sales from quotation to delivery, applies an advance, settles an invoice and refunds a return', function () {
    $user = User::factory()->create();
    $cash = DB::table('journal_accounts')->where('team_id', $user->currentTeam->id)->where('code', '1101')->value('id');
    $quote = saveTrade($this, $user, 'penjualan', [
        'kind' => 'quotation', 'partner' => 'PT Pembeli',
        'lines' => [['name' => 'Produk A', 'quantity' => 10, 'unit_price' => 100000]],
    ]);
    $order = saveTrade($this, $user, 'penjualan', [
        'kind' => 'order', 'source_id' => $quote, 'lines' => sourceTradeLines($quote, 10),
    ]);
    saveTrade($this, $user, 'penjualan', [
        'kind' => 'advance', 'source_id' => $order, 'amount' => 200000, 'account_id' => $cash,
    ]);
    $delivery = saveTrade($this, $user, 'penjualan', [
        'kind' => 'delivery', 'source_id' => $order, 'lines' => sourceTradeLines($order, 6),
    ]);
    $invoice = saveTrade($this, $user, 'penjualan', [
        'kind' => 'invoice', 'source_id' => $delivery, 'lines' => sourceTradeLines($delivery, 6),
    ]);
    saveTrade($this, $user, 'penjualan', ['kind' => 'payment', 'source_id' => $invoice, 'amount' => 100000, 'account_id' => $cash]);
    saveTrade($this, $user, 'penjualan', ['kind' => 'payment', 'source_id' => $invoice, 'amount' => 300000, 'account_id' => $cash]);
    saveTrade($this, $user, 'penjualan', ['kind' => 'return', 'source_id' => $invoice, 'lines' => sourceTradeLines($invoice, 1)]);

    $show = route('trade.show', [$user->currentTeam, 'penjualan', $invoice]);
    $this->get($show)->assertInertia(fn (Assert $page) => $page
        ->where('document.openAmount', -100000)->where('document.appliedAdvance', 200000));
    saveTrade($this, $user, 'penjualan', ['kind' => 'refund', 'source_id' => $invoice, 'amount' => 100000, 'account_id' => $cash]);
    $this->get($show)->assertInertia(fn (Assert $page) => $page->where('document.openAmount', 0));

    $invoiceJournal = DB::table('trade_documents')->where('id', $invoice)->value('journal_entry_id');
    $this->assertDatabaseHas('journal_entry_lines', [
        'journal_entry_id' => $invoiceJournal, 'account_name' => 'Piutang Usaha', 'side' => 'debit', 'amount' => 400000,
    ]);
    $this->assertDatabaseHas('journal_entry_lines', [
        'journal_entry_id' => $invoiceJournal, 'account_name' => 'Uang Muka Pelanggan', 'side' => 'debit', 'amount' => 200000,
    ]);
    $this->assertDatabaseCount('journal_entries', 6);
});

it('receives purchase orders in parts and prevents billing, paying or returning more than the source', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $cash = DB::table('journal_accounts')->where('team_id', $team->id)->where('code', '1101')->value('id');
    $order = saveTrade($this, $user, 'pembelian', [
        'kind' => 'order', 'partner' => 'PT Pemasok',
        'lines' => [['name' => 'Bahan Baku', 'quantity' => 10, 'unit_price' => 80000]],
    ]);
    $receipt = saveTrade($this, $user, 'pembelian', ['kind' => 'receipt', 'source_id' => $order, 'lines' => sourceTradeLines($order, 5)]);
    $invoice = saveTrade($this, $user, 'pembelian', ['kind' => 'invoice', 'source_id' => $receipt, 'lines' => sourceTradeLines($receipt, 5)]);
    $store = route('trade.store', [$team, 'pembelian']);
    $post = fn (array $fields) => $this->post($store, [
        'date' => '2026-11-07', 'request_id' => (string) Str::uuid(), ...$fields,
    ]);

    $post(['kind' => 'invoice', 'source_id' => $receipt, 'lines' => sourceTradeLines($receipt, 1)])->assertSessionHasErrors('lines');
    $post(['kind' => 'payment', 'source_id' => $invoice, 'amount' => 400001, 'account_id' => $cash])->assertSessionHasErrors('amount');
    $post(['kind' => 'return', 'source_id' => $invoice, 'lines' => sourceTradeLines($invoice, 6)])->assertSessionHasErrors('lines');

    saveTrade($this, $user, 'pembelian', ['kind' => 'return', 'source_id' => $invoice, 'lines' => sourceTradeLines($invoice, 2)]);
    saveTrade($this, $user, 'pembelian', ['kind' => 'payment', 'source_id' => $invoice, 'amount' => 240000, 'account_id' => $cash]);
    $this->get(route('trade.show', [$team, 'pembelian', $invoice]))
        ->assertInertia(fn (Assert $page) => $page->where('document.openAmount', 0));
    $this->assertDatabaseHas('journal_entry_lines', ['account_name' => 'Persediaan Barang', 'side' => 'credit', 'amount' => 160000]);
    $this->assertDatabaseHas('journal_entry_lines', ['account_name' => 'Utang Usaha', 'side' => 'debit', 'amount' => 240000]);

    $other = User::factory()->create();
    $this->actingAs($other)->get(route('trade.show', [$other->currentTeam, 'pembelian', $invoice]))->assertNotFound();
    $this->get(route('trade.index', [$team, 'pembelian']))->assertForbidden();
});

it('scopes source documents to their company and direction and treats identical retries as one posting', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $team = $user->currentTeam;
    $order = saveTrade($this, $other, 'penjualan', [
        'kind' => 'order', 'partner' => 'Pelanggan perusahaan lain',
        'lines' => [['name' => 'Barang', 'quantity' => 1, 'unit_price' => 100000]],
    ]);

    $url = route('trade.store', [$team, 'penjualan']);
    $this->actingAs($user)->post($url, [
        'request_id' => (string) Str::uuid(), 'kind' => 'delivery', 'date' => '2026-11-07',
        'source_id' => $order, 'lines' => sourceTradeLines($order, 1),
    ])->assertSessionHasErrors('source_id');

    $payload = [
        'request_id' => (string) Str::uuid(), 'kind' => 'invoice', 'date' => '2026-11-07',
        'partner' => 'Pelanggan sendiri', 'due_date' => '2026-11-30',
        'lines' => [['name' => 'Jasa', 'quantity' => 1, 'unit_price' => 300000]],
    ];
    $first = $this->post($url, $payload)->assertSessionHasNoErrors()->assertRedirect();
    $this->post($url, $payload)->assertRedirect($first->headers->get('Location'));
    $this->post($url, [...$payload, 'partner' => 'Pelanggan berbeda'])->assertSessionHasErrors('request_id');
    $this->assertDatabaseCount('journal_entries', 1);
    $this->assertDatabaseHas('trade_documents', ['request_id' => $payload['request_id'], 'due_date' => '2026-11-30']);

    $this->get(route('trade.index', [$team, 'penjualan']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('documents.data', 1)
            ->where('documents.data.0.openAmount', 300000));
    $this->get(route('trade.index', [$team, 'pembelian']))
        ->assertInertia(fn (Assert $page) => $page->has('documents.data', 0));
});

it('allows marking an active asset as cash and protects control account classification', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $this->actingAs($user)->post(route('chart-of-accounts.store', $team), [
        'code' => '1104-MANDIRI', 'name' => 'Bank Mandiri', 'type' => 'asset', 'active' => true, 'is_cash' => true,
    ])->assertSessionHasNoErrors();
    $cash = DB::table('journal_accounts')->where('team_id', $team->id)->where('code', '1104-MANDIRI')->value('id');

    $invoice = saveTrade($this, $user, 'penjualan', [
        'kind' => 'invoice', 'partner' => 'Pelanggan',
        'lines' => [['name' => 'Jasa', 'quantity' => 1, 'unit_price' => 50000]],
    ]);
    saveTrade($this, $user, 'penjualan', [
        'kind' => 'payment', 'source_id' => $invoice, 'amount' => 50000, 'account_id' => $cash,
    ]);
    $this->assertDatabaseHas('journal_entry_lines', ['account_code' => '1104-MANDIRI', 'side' => 'debit', 'amount' => 50000]);
    $control = DB::table('journal_accounts')->where('team_id', $team->id)->where('trade_role', 'receivable')->first();
    $this->put(route('chart-of-accounts.update', [$team, $control->id]), [
        'code' => $control->code, 'name' => $control->name, 'type' => 'expense', 'active' => true,
    ])->assertSessionHasErrors('active');
});

it('stores safe Quill formatting and renders existing plain notes through the same document detail', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $payload = [
        'kind' => 'quotation', 'partner' => 'Pelanggan', 'date' => '2026-11-07',
        'lines' => [['name' => 'Jasa', 'quantity' => 1, 'unit_price' => 50000]],
    ];
    $runs = [
        ['insert' => 'Keterangan '],
        ['insert' => 'penting', 'attributes' => ['bold' => true]],
        ['insert' => "\nBaris kedua\n"],
    ];
    $quote = saveTrade($this, $user, 'penjualan', [...$payload, 'notes_delta' => json_encode($runs)]);
    $this->get(route('trade.show', [$team, 'penjualan', $quote]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('document.notes', "Keterangan penting\nBaris kedua")
            ->where('document.notesRuns.0.insert', 'Keterangan ')
            ->where('document.notesRuns.1.attributes.bold', true)
        );

    $plain = saveTrade($this, $user, 'penjualan', [...$payload, 'notes' => '<b>Teks lama</b>']);
    $this->get(route('trade.show', [$team, 'penjualan', $plain]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('document.notes', '<b>Teks lama</b>')
            ->has('document.notesRuns', 0)
        );

    $this->post(route('trade.store', [$team, 'penjualan']), [
        ...$payload, 'request_id' => (string) Str::uuid(),
        'notes_delta' => json_encode([['insert' => ['image' => 'https://example.test/image']]]),
    ])->assertSessionHasErrors('notes_delta');
    $this->post(route('trade.store', [$team, 'penjualan']), [
        ...$payload, 'request_id' => (string) Str::uuid(),
        'notes_delta' => json_encode([['insert' => str_repeat('a', 501)]]),
    ])->assertSessionHasErrors('notes_delta');
});
