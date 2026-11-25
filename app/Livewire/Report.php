<?php

namespace App\Livewire;

use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Report extends Component
{
    #[Url(as: 'bulan')]
    public string $month = '';

    public function mount(): void
    {
        $this->month = $this->parse($this->month)->format('Y-m');
    }

    private function parse(string $m): Carbon
    {
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m)
            ? Carbon::createFromFormat('Y-m-d', $m . '-01')->startOfMonth()
            : now()->startOfMonth();
    }

    public function prev(): void
    {
        $this->month = $this->parse($this->month)->subMonth()->format('Y-m');
    }

    public function next(): void
    {
        $next = $this->parse($this->month)->addMonth();
        if ($next->lte(now()->startOfMonth())) {
            $this->month = $next->format('Y-m');
        }
    }

    #[On('transaction-saved')]
    public function refresh(): void {}

    /** @return array{income:int, expense:int} */
    private function totals(int $hid, Carbon $start): array
    {
        $rows = Transaction::where('household_id', $hid)
            ->whereBetween('date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()])
            ->select('type', DB::raw('SUM(amount) as total'))->groupBy('type')->pluck('total', 'type');

        return ['income' => (int) ($rows['income'] ?? 0), 'expense' => (int) ($rows['expense'] ?? 0)];
    }

    private function breakdown(int $hid, Carbon $start, string $type)
    {
        $rows = Transaction::where('transactions.household_id', $hid)->where('transactions.type', $type)
            ->whereBetween('transactions.date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()])
            ->join('categories', 'categories.id', '=', 'transactions.category_id')
            ->select('categories.name', 'categories.color', 'categories.icon', DB::raw('SUM(transactions.amount) as total'), DB::raw('COUNT(*) as n'))
            ->groupBy('categories.id', 'categories.name', 'categories.color', 'categories.icon')
            ->orderByDesc('total')->get();

        $sum = max(1, (int) $rows->sum('total'));

        return $rows->map(function ($r) use ($sum) {
            $r->pct = $r->total / $sum * 100;
            return $r;
        });
    }

    public function render()
    {
        $user = auth()->user();
        $hid = $user->household_id;
        $start = $this->parse($this->month);

        $cur = $this->totals($hid, $start);
        $prev = $this->totals($hid, $start->copy()->subMonth());

        $byUser = collect();
        if ($user->partner()) {
            $byUser = Transaction::where('transactions.household_id', $hid)->where('transactions.type', 'expense')
                ->whereBetween('transactions.date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()])
                ->join('users', 'users.id', '=', 'transactions.user_id')
                ->select('users.name', DB::raw('SUM(transactions.amount) as total'))
                ->groupBy('users.id', 'users.name')->orderByDesc('total')->get();
        }

        return view('livewire.report', [
            'start' => $start,
            'isCurrent' => $start->isSameMonth(now()),
            'income' => $cur['income'],
            'expense' => $cur['expense'],
            'balance' => $cur['income'] - $cur['expense'],
            'prevIncome' => $prev['income'],
            'prevExpense' => $prev['expense'],
            'expenseRows' => $this->breakdown($hid, $start, 'expense'),
            'incomeRows' => $this->breakdown($hid, $start, 'income'),
            'byUser' => $byUser,
            'count' => Transaction::where('household_id', $hid)
                ->whereBetween('date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()])->count(),
        ])->title('Laporan');
    }
}
