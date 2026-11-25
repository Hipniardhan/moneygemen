<?php

namespace Tests\Feature;

use App\Livewire\Report;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\HouseholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name = 'Budi'): User
    {
        return app(HouseholdService::class)->register($name, strtolower($name) . '@mail.test', 'password123');
    }

    private function tx(User $u, string $cat, int $amount, string $type, string $date, ?string $desc = null): Transaction
    {
        return Transaction::create([
            'household_id' => $u->household_id, 'user_id' => $u->id,
            'category_id' => Category::where('household_id', $u->household_id)->where('name', $cat)->value('id'),
            'type' => $type, 'amount' => $amount, 'date' => $date, 'description' => $desc,
        ]);
    }

    public function test_report_totals_comparison_and_breakdown(): void
    {
        $u = $this->user();
        $thisMonth = now()->startOfMonth();
        $last = $thisMonth->copy()->subMonth();

        $this->tx($u, 'Gaji & Upah', 5000000, 'income', $thisMonth->toDateString());
        $this->tx($u, 'Tagihan', 750000, 'expense', $thisMonth->toDateString());
        $this->tx($u, 'Makanan & Minuman', 250000, 'expense', $thisMonth->toDateString());
        $this->tx($u, 'Tagihan', 500000, 'expense', $last->toDateString()); // bulan lalu
        $this->tx($u, 'Tagihan', 999, 'expense', $thisMonth->copy()->subMonths(3)->toDateString()); // di luar

        $c = Livewire::actingAs($u)->test(Report::class)
            ->assertSee('Rp 5.000.000')->assertSee('Rp 1.000.000')->assertSee('Rp 4.000.000')
            ->assertSee('+100% vs bulan lalu')   // 1.000.000 vs 500.000
            ->assertSeeInOrder(['Tagihan', '75%', 'Makanan & Minuman', '25%'])
            ->assertSee('80%');                   // tingkat tabungan

        // pindah ke bulan lalu
        $c->call('prev')->assertSee('Rp 500.000')->assertDontSee('Rp 5.000.000');
    }

    public function test_report_cannot_go_into_the_future_and_rejects_bad_month(): void
    {
        $u = $this->user();
        $c = Livewire::actingAs($u)->test(Report::class)->call('next');
        $this->assertSame(now()->format('Y-m'), $c->get('month'));

        Livewire::actingAs($u)->withQueryParams(['bulan' => 'ngawur'])->test(Report::class)
            ->assertSet('month', now()->format('Y-m'));
    }

    public function test_report_shows_per_person_split_only_with_partner(): void
    {
        $a = $this->user('Rina');
        $b = $this->user('Budi');
        $this->tx($a, 'Tagihan', 100000, 'expense', now()->toDateString());
        Livewire::actingAs($a)->test(Report::class)->assertDontSee('Pengeluaran per orang');

        $svc = app(HouseholdService::class);
        $svc->acceptInvite($b, $svc->createInvite($a)->code);
        $this->tx($b->fresh(), 'Tagihan', 300000, 'expense', now()->toDateString());

        Livewire::actingAs($a->fresh())->test(Report::class)
            ->assertSee('Pengeluaran per orang')->assertSee('Budi')->assertSee('Rp 300.000');
    }

    public function test_csv_export_content_scope_and_injection_guard(): void
    {
        $a = $this->user('Rina');
        $b = $this->user('Budi');
        $this->tx($a, 'Tagihan', 1250000, 'expense', '2026-03-05', '=HYPERLINK("http://evil")');
        $this->tx($a, 'Gaji & Upah', 8000000, 'income', '2026-03-25', 'Gaji; Maret');
        $this->tx($a, 'Tagihan', 1, 'expense', '2026-02-01', 'februari');
        $this->tx($b, 'Tagihan', 777, 'expense', '2026-03-05', 'milik orang lain');

        $res = $this->actingAs($a)->get('/ekspor.csv?bulan=2026-03');
        $res->assertOk();
        $csv = $res->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Tanggal;Tipe;Kategori;Nominal;Catatan;"Dicatat oleh"', $csv);
        $this->assertStringContainsString('2026-03-05;Pengeluaran;Tagihan;1250000;', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);                 // rumus dinetralkan
        $this->assertStringNotContainsString(';=HYPERLINK', $csv);
        $this->assertStringContainsString('"Gaji; Maret"', $csv);                 // pemisah di dalam sel di-quote
        $this->assertStringNotContainsString('februari', $csv);                   // di luar bulan
        $this->assertStringNotContainsString('milik orang lain', $csv);           // household lain

        $all = $this->actingAs($a)->get('/ekspor.csv')->streamedContent();
        $this->assertStringContainsString('februari', $all);
        $this->assertStringNotContainsString('milik orang lain', $all);
    }

    public function test_export_requires_login(): void
    {
        $this->get('/ekspor.csv')->assertRedirect('/masuk');
        $this->get('/laporan')->assertRedirect('/masuk');
    }
}
