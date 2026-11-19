<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('renders a printable PDF and scopes evidence to its company and document', function () {
    Storage::fake('local');
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $data = ['request_id' => (string) Str::uuid(), 'kind' => 'invoice', 'partner' => 'Pelanggan contoh', 'date' => '2026-11-19', 'lines' => [['name' => 'Jasa', 'quantity' => 2, 'unit_price' => 10000]]];
    $this->actingAs($owner)->post(route('trade.store', [$team, 'penjualan']), $data)->assertSessionHasNoErrors();
    $id = DB::table('trade_documents')->where('request_id', $data['request_id'])->value('id');
    $args = [$team, 'penjualan', $id];
    $pdf = $this->get(route('trade.pdf', $args))->assertOk()->assertHeader('Content-Type', 'application/pdf')->getContent();
    expect($pdf)->toStartWith('%PDF-');
    $this->post(route('trade.attachments.upload', $args), ['file' => UploadedFile::fake()->createWithContent('bukti.pdf', $pdf)])->assertRedirect()->assertSessionHasNoErrors();
    $attachment = DB::table('trade_attachments')->firstOrFail();
    $url = route('trade.attachments.download', [...$args, $attachment->id]);
    expect($this->get($url)->assertDownload('bukti.pdf')->streamedContent())->toBe($pdf);
    $this->get(route('trade.pdf', [$team, 'pembelian', $id]))->assertNotFound();
    $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
});
