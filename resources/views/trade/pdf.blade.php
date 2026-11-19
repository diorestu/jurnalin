<!doctype html>
<html lang="id"><head><meta charset="utf-8"><title>{{ $title }} {{ $number }}</title>
<style>
@page { margin: 44pt; }
body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #152438; }
header { border-bottom: 2pt solid #021d4f; padding-bottom: 14pt; } h1 { margin-bottom: 3pt; }
table { width: 100%; border-collapse: collapse; margin: 18pt 0; table-layout: fixed; }
th,td { border-bottom: 1pt solid #ddd; padding: 7pt 4pt; text-align: left; overflow-wrap: break-word; }
th { background: #f1f4f8; } .amount { text-align: right; } .notes { white-space: pre-wrap; }
</style></head><body>
<header><strong>{{ $company }}</strong><h1>{{ $title }}</h1>{{ $number }}</header>
<p>{{ $partnerLabel }} &nbsp; <strong>{{ $doc->partner }}</strong></p>
<p>Tanggal &nbsp; {{ $doc->date }} @if($doc->due_date) &nbsp; · &nbsp; Jatuh tempo {{ $doc->due_date }} @endif</p>
@if($doc->reference)<p>Referensi &nbsp; {{ $doc->reference }}</p>@endif
@if($origin)<p>Dokumen sumber &nbsp; {{ $origin }}</p>@endif
@if($lines->isNotEmpty())
<table><thead><tr><th>Barang / jasa</th><th style="width:12%">Jumlah</th>@unless($shipping)<th class="amount">Harga satuan (Rp)</th><th class="amount">Subtotal (Rp)</th>@endunless</tr></thead><tbody>
@foreach($lines as $line)<tr><td>{{ $line->name }}@if($line->unit_discount || $line->tax_code)<br><small>Diskon/unit Rp {{ number_format($line->unit_discount,0,',','.') }}<br>{{ $line->tax_code }} {{ $line->tax_bps/100 }}% · Rp {{ number_format($line->tax_amount,0,',','.') }} ({{ $line->tax_included ? 'termasuk' : 'di luar harga' }})</small>@endif</td><td>{{ number_format($line->quantity,0,',','.') }}</td>@unless($shipping)<td class="amount">{{ number_format($line->unit_price,0,',','.') }}</td><td class="amount">{{ number_format($line->total,0,',','.') }}</td>@endunless</tr>@endforeach
</tbody></table>@endif
@unless($shipping)@if($lines->sum('tax_amount'))<p class="amount">Dasar setelah diskon Rp {{ number_format($doc->total-$lines->sum('tax_amount'),0,',','.') }} · PPN Rp {{ number_format($lines->sum('tax_amount'),0,',','.') }}</p>@endif<h3 class="amount">Total Rp {{ number_format($doc->total,0,',','.') }}</h3>@endunless
@if($doc->notes)<p class="notes">{{ $doc->notes }}</p>@endif
</body></html>
