<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\Transaction;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Transactions extends Component
{
    #[Url(as: 'tipe')]
    public string $type = 'all';

    #[Url(as: 'kategori')]
    public string $categoryId = '';

    #[Url(as: 'cari')]
    public string $search = '';

    #[Url(as: 'urut')]
    public string $sort = 'newest';

    public int $perPage = 20;

    public function updated($name): void
    {
        if (in_array($name, ['type', 'categoryId', 'search', 'sort'], true)) {
            $this->perPage = 20;
        }
        if ($name === 'type') {
            $this->categoryId = '';
        }
    }

    public function more(): void
    {
        $this->perPage += 20;
    }

    #[On('transaction-saved')]
    public function refresh(): void {}

    public function render()
    {
        $user = auth()->user();
        $hid = $user->household_id;

        $query = Transaction::with(['category', 'user'])->where('household_id', $hid);

        if (in_array($this->type, ['income', 'expense'], true)) {
            $query->where('type', $this->type);
        }
        if ($this->categoryId !== '') {
            $query->where('category_id', (int) $this->categoryId);
        }
        if (trim($this->search) !== '') {
            $term = '%' . addcslashes(trim($this->search), '%_\\') . '%';
            $query->where(function ($q) use ($term) {
                $q->where('description', 'like', $term)
                    ->orWhereHas('category', fn ($c) => $c->where('name', 'like', $term));
            });
        }

        match ($this->sort) {
            'oldest' => $query->orderBy('date')->orderBy('id'),
            'largest' => $query->orderByDesc('amount')->orderByDesc('id'),
            'smallest' => $query->orderBy('amount')->orderBy('id'),
            default => $query->orderByDesc('date')->orderByDesc('id'),
        };

        $items = $query->limit($this->perPage + 1)->get();
        $hasMore = $items->count() > $this->perPage;
        $items = $items->take($this->perPage);

        $categories = Category::where('household_id', $hid)
            ->when(in_array($this->type, ['income', 'expense'], true), fn ($q) => $q->where('type', $this->type))
            ->orderBy('name')->get();

        return view('livewire.transactions', [
            'items' => $items,
            'hasMore' => $hasMore,
            'categories' => $categories,
            'hasPartner' => (bool) $user->partner(),
        ])->title('Transaksi');
    }
}
