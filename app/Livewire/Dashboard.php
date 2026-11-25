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
        $chartMax = max(1, (int) collect($chart)->flatten()->max('total'));

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
            'chartMax' => $chartMax,
            'recent' => $recent,
            'hasPartner' => (bool) auth()->user()->partner(),
        ])->title('Beranda');
    }
}
