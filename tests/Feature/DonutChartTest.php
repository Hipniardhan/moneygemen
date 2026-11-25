<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\HouseholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DonutChartTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return app(HouseholdService::class)->register('Budi', 'budi@mail.test', 'password123');
    }

    private function tx(User $u, string $cat, int $amount, string $type = 'expense'): void
    {
        Transaction::create([
            'household_id' => $u->household_id, 'user_id' => $u->id,
            'category_id' => Category::where('household_id', $u->household_id)->where('name', $cat)->value('id'),
            'type' => $type, 'amount' => $amount, 'date' => now()->toDateString(),
        ]);
    }

    public function test_slices_cover_the_whole_ring_and_add_up_to_total(): void
    {
        $u = $this->user();
        $this->tx($u, 'Gaji & Upah', 3000000, 'income');
        foreach (['Tagihan' => 400, 'Hiburan' => 300, 'Belanja' => 200, 'Kesehatan' => 100] as $cat => $n) {
            $this->tx($u, $cat, $n * 1000);
        }

        $donuts = Livewire::actingAs($u)->test(Dashboard::class)->viewData('donuts');

        $this->assertCount(1, $donuts['income']);
        $this->assertSame(100.0, (float) $donuts['income'][0]['pct']);
        $this->assertSame(1000000, collect($donuts['expense'])->sum('amount'));
        $this->assertSame(['Tagihan', 'Hiburan', 'Belanja', 'Kesehatan'], collect($donuts['expense'])->pluck('name')->all());
        $this->assertEqualsWithDelta(100, collect($donuts['expense'])->sum('pct'), 1.5);

        // tiap irisan dimulai tepat di akhir irisan sebelumnya (tanpa tumpang tindih)
        $circ = 2 * M_PI * 48;
        $expectOffset = 0.0;
        foreach ($donuts['expense'] as $s) {
            $this->assertEqualsWithDelta($expectOffset, -$s['offset'], 0.02);
            $expectOffset += $s['amount'] / 1000000 * $circ;
        }
    }

    public function test_remaining_categories_are_grouped_as_lainnya(): void
    {
        $u = $this->user();
        $names = ['Tagihan', 'Hiburan', 'Belanja', 'Kesehatan', 'Pendidikan', 'Makanan & Minuman', 'Pengeluaran Lainnya'];
        foreach ($names as $i => $n) {
            $this->tx($u, $n, ($i + 1) * 10000);
        }

        $c = Livewire::actingAs($u)->test(Dashboard::class);
        $segs = collect($c->viewData('donuts')['expense']);

        $this->assertCount(6, $segs); // 5 teratas + Lainnya
        $this->assertSame('Lainnya', $segs->last()['name']);
        $this->assertSame(10000 + 20000, $segs->last()['amount']); // dua kategori terkecil
        $this->assertSame(280000, $segs->sum('amount'));
        $c->assertSee('Lainnya');
    }

    public function test_tiny_slice_has_no_icon_on_ring_but_is_listed(): void
    {
        $u = $this->user();
        $this->tx($u, 'Tagihan', 1000000);
        $this->tx($u, 'Hiburan', 10000); // ±1%

        $segs = collect(Livewire::actingAs($u)->test(Dashboard::class)->viewData('donuts')['expense']);

        $this->assertTrue($segs->firstWhere('name', 'Tagihan')['showIcon']);
        $this->assertFalse($segs->firstWhere('name', 'Hiburan')['showIcon']);
        $this->assertSame(1.0, (float) $segs->firstWhere('name', 'Hiburan')['pct']);
    }

    public function test_empty_state_and_single_type(): void
    {
        $u = $this->user();
        $c = Livewire::actingAs($u)->test(Dashboard::class);
        $this->assertSame([], $c->viewData('donuts')['income']);
        $this->assertSame([], $c->viewData('donuts')['expense']);
        $c->assertSee('Belum ada transaksi')->assertDontSee('class="donuts"', false);

        $this->tx($u, 'Gaji & Upah', 5000000, 'income');
        Livewire::actingAs($u)->test(Dashboard::class)
            ->assertSee('class="donuts"', false)->assertSee('5jt')->assertSee('Belum ada pengeluaran');
    }
}
