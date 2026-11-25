<?php

namespace App\Livewire;

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Dashboard extends Component
{
    public string $tab = 'all';

    #[Url(as: 'periode')]
    public string $period = 'all'; // all | month

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['all', 'income', 'expense'], true) ? $tab : 'all';
    }

    public function setPeriod(string $period): void
    {
        $this->period = $period === 'month' ? 'month' : 'all';
    }

    #[On('transaction-saved')]
    public function refresh(): void {}

    /**
     * Irisan donat: 5 kategori teratas + "Lainnya" untuk sisanya.
     * Geometri dihitung di sini (lingkaran r=48 pada kanvas 120x120, mulai dari jam 12).
     *
     * @param  \Illuminate\Support\Collection  $rows  baris teratas ber-total
     * @return array<int, array<string, mixed>>
     */
    private function donut($rows, int $total): array
    {
        if ($total <= 0) {
            return [];
        }

        $r = 48;
        $circ = 2 * M_PI * $r;

        $items = $rows->map(fn ($x) => [
            'k' => (string) $x->id, 'name' => $x->name, 'icon' => $x->icon, 'color' => $x->color, 'amount' => (int) $x->total,
        ])->values();

        $rest = $total - $items->sum('amount');
        if ($rest > 0) {
            $items->push(['k' => 'rest', 'name' => 'Lainnya', 'icon' => '⋯', 'color' => '#94a3b8', 'amount' => $rest]);
        }

        $n = $items->count();
        $acc = 0.0;
        $segs = [];
        foreach ($items as $it) {
            $frac = $it['amount'] / $total;
            $len = $frac * $circ;
            $gap = $n > 1 ? min(2.0, $len * 0.3) : 0.0; // celah kecil antar irisan
            $draw = max($len - $gap, 0.5);
            $mid = ($acc + $frac / 2) * 2 * M_PI - M_PI / 2;

            $segs[] = $it + [
                'pct' => round($frac * 100),
                'dash' => round($draw, 2),
                'rest' => round($circ - $draw, 2),
                'offset' => round(-$acc * $circ, 2),
                'ix' => round(60 + $r * cos($mid), 2),
                'iy' => round(60 + $r * sin($mid), 2),
                'showIcon' => $frac >= 0.09, // irisan terlalu kecil tidak muat ikon; ikonnya ada di daftar
            ];
            $acc += $frac;
        }

        return $segs;
    }

    public function render()
    {
        $hid = auth()->user()->household_id;

        $base = Transaction::where('transactions.household_id', $hid);
        if ($this->period === 'month') {
            $base->whereBetween('transactions.date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()]);
        }

        $totals = (clone $base)->select('type', DB::raw('SUM(amount) as total'))->groupBy('type')->pluck('total', 'type');
        $income = (int) ($totals['income'] ?? 0);
        $expense = (int) ($totals['expense'] ?? 0);

        $top = (clone $base)->where('transactions.type', 'expense')
            ->join('categories', 'categories.id', '=', 'transactions.category_id')
            ->select('categories.name', 'categories.color', 'categories.icon', DB::raw('SUM(transactions.amount) as total'))
            ->groupBy('categories.id', 'categories.name', 'categories.color', 'categories.icon')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        // akumulasi per kategori (5 teratas tiap tipe) untuk grafik kolom
        $acc = (clone $base)
            ->join('categories', 'categories.id', '=', 'transactions.category_id')
            ->select('transactions.type', 'categories.id', 'categories.name', 'categories.color', 'categories.icon', DB::raw('SUM(transactions.amount) as total'))
            ->groupBy('transactions.type', 'categories.id', 'categories.name', 'categories.color', 'categories.icon')
            ->orderByDesc('total')
            ->get()
            ->groupBy('type');
        $chart = [
            'income' => ($acc['income'] ?? collect())->take(5),
            'expense' => ($acc['expense'] ?? collect())->take(5),
        ];
        $chartTotals = [
            'income' => (int) ($acc['income'] ?? collect())->sum('total'),
            'expense' => (int) ($acc['expense'] ?? collect())->sum('total'),
        ];
        $donuts = [
            'income' => $this->donut($chart['income'], $chartTotals['income']),
            'expense' => $this->donut($chart['expense'], $chartTotals['expense']),
        ];

        $recent = Transaction::with(['category', 'user'])
            ->where('household_id', $hid)
            ->when($this->tab !== 'all', fn ($q) => $q->where('type', $this->tab))
            ->orderByDesc('date')->orderByDesc('id')
            ->limit(7)
            ->get();

        return view('livewire.dashboard', [
            'income' => $income,
            'expense' => $expense,
            'balance' => $income - $expense,
            'top' => $top,
            'topMax' => max(1, (int) ($top->first()->total ?? 1)),
            'chart' => $chart,
            'chartTotals' => $chartTotals,
            'donuts' => $donuts,
            'recent' => $recent,
            'hasPartner' => (bool) auth()->user()->partner(),
        ])->title('Beranda');
    }
}
