<?php

namespace App\Http\Controllers;

use App\Actions\Accounting\RecordAccountingAudit;
use App\Enums\TradeDirection;
use App\Enums\TradeKind;
use App\Models\Team;
use Dompdf\Dompdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TradeFileController extends Controller
{
    private function document(Team $team, TradeDirection $direction, int $id): \stdClass
    {
        return DB::table('trade_documents')->where('team_id', $team->id)->where('direction', $direction->value)->where('id', $id)->firstOrFail();
    }

    public function pdf(Team $current_team, TradeDirection $direction, int $document): Response
    {
        $doc = $this->document($current_team, $direction, $document);
        $kind = TradeKind::from($doc->kind);
        $number = $kind->number($direction, $document);
        $origin = $doc->source_id ? $this->document($current_team, $direction, $doc->source_id) : null;
        $title = $kind === TradeKind::Payment && $direction === TradeDirection::Sales ? 'Kuitansi penerimaan' : $kind->label($direction);
        if ($doc->opening_balance_id !== null) {
            $title = $direction === TradeDirection::Sales ? 'Piutang awal' : 'Utang awal';
        }
        $pdf = new Dompdf;
        $pdf->setPaper('A4');
        $pdf->loadHtml(view('trade.pdf', [
            'company' => $current_team->name, 'doc' => $doc, 'number' => $number,
            'title' => $title,
            'origin' => $origin ? TradeKind::from($origin->kind)->number($direction, $origin->id) : null,
            'partnerLabel' => $direction === TradeDirection::Sales ? 'Pelanggan' : 'Pemasok',
            'shipping' => in_array($kind, [TradeKind::Delivery, TradeKind::Receipt], true),
            'lines' => DB::table('trade_document_lines')->where('document_id', $document)->orderBy('id')->get(),
        ])->render(), 'UTF-8');
        $pdf->render();

        return response($pdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$number.'.pdf"', 'Cache-Control' => 'private, no-store']);
    }

    public function upload(Request $request, Team $current_team, TradeDirection $direction, int $document): RedirectResponse
    {
        $this->document($current_team, $direction, $document);
        $request->validate(['file' => ['required', 'file', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:5120']]);
        $file = $request->file('file');
        $path = $file->store("trade-attachments/$current_team->id/$document", 'local');
        abort_if($path === false, 500);
        try {
            DB::transaction(function () use ($request, $file, $path, $document, $current_team) {
                $name = mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 250);
                $id = DB::table('trade_attachments')->insertGetId(['document_id' => $document, 'uploaded_by' => $request->user()->id, 'name' => $name, 'path' => $path, 'mime' => $file->getMimeType(), 'size' => $file->getSize(), 'created_at' => now()]);
                app(RecordAccountingAudit::class)->record($current_team->id, $request->user()->id, 'attachment.uploaded', 'trade_document', $document, null, ['attachment_id' => $id, 'name' => $name]);
            });
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }

        return to_route('trade.show', [$current_team, $direction, $document]);
    }

    public function download(Team $current_team, TradeDirection $direction, int $document, int $attachment): StreamedResponse
    {
        $this->document($current_team, $direction, $document);
        $file = DB::table('trade_attachments')->where('document_id', $document)->where('id', $attachment)->firstOrFail();

        return Storage::disk('local')->download($file->path, $file->name, ['Content-Type' => $file->mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}
