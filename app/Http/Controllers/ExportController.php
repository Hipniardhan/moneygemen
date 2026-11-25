<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /** Ekspor CSV: ?bulan=2026-03 untuk satu bulan, tanpa parameter untuk semua data. */
    public function csv(Request $request): StreamedResponse
    {
        $user = $request->user();
        $query = Transaction::with(['category', 'user'])
            ->where('household_id', $user->household_id)
            ->orderBy('date')->orderBy('id');

        $label = 'semua';
        $bulan = (string) $request->query('bulan', '');
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $bulan)) {
            $start = Carbon::createFromFormat('Y-m-d', $bulan . '-01')->startOfMonth();
            $query->whereBetween('date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()]);
            $label = $bulan;
        }

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
            // titik koma: Excel dengan pengaturan regional Indonesia memakai pemisah ini
            fputcsv($out, ['Tanggal', 'Tipe', 'Kategori', 'Nominal', 'Catatan', 'Dicatat oleh'], ';');

            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $t) {
                    fputcsv($out, [
                        $t->date->toDateString(),
                        $t->type === 'income' ? 'Pemasukan' : 'Pengeluaran',
                        $this->safe($t->category->name),
                        $t->amount,
                        $this->safe((string) $t->description),
                        $this->safe($t->user->name),
                    ], ';');
                }
            });
            fclose($out);
        }, "moneygemen-{$label}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Cegah CSV/formula injection: sel yang diawali = + - @ dianggap rumus oleh Excel. */
    private function safe(string $v): string
    {
        return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
    }
}
