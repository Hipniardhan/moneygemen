<?php

namespace App\Livewire;

use App\Livewire\Concerns\BlocksDemo;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\PartnerNotifier;
use Livewire\Attributes\On;
use Livewire\Component;

class TransactionForm extends Component
{
    use BlocksDemo;

    public bool $show = false;
    public ?int $editingId = null;

    public string $type = 'expense';
    public string $amount = '';
    public string $categoryId = '';
    public string $date = '';
    public string $description = '';

    #[On('open-transaction')]
    public function open(?int $id = null): void
    {
        if ($this->denyDemo()) {
            return;
        }

        $this->resetValidation();
        $this->reset(['editingId', 'amount', 'categoryId', 'description']);
        $this->type = 'expense';
        $this->date = now()->toDateString();

        if ($id) {
            $t = Transaction::where('household_id', auth()->user()->household_id)->findOrFail($id);
            $this->editingId = $t->id;
            $this->type = $t->type;
            $this->amount = (string) $t->amount;
            $this->categoryId = (string) $t->category_id;
            $this->date = $t->date->toDateString();
            $this->description = (string) $t->description;
        }

        $this->show = true;
    }

    public function close(): void
    {
        $this->show = false;
    }

    public function setType(string $type): void
    {
        $type = $type === 'income' ? 'income' : 'expense';
        if ($type !== $this->type) {
            $this->type = $type;
            $this->categoryId = '';
        }
    }

    public function save(PartnerNotifier $notifier): void
    {
        if ($this->denyDemo()) {
            return;
        }

        $user = auth()->user();
        $this->amount = preg_replace('/\D/', '', $this->amount);

        $data = $this->validate([
            'type' => 'required|in:income,expense',
            'amount' => 'required|integer|min:1|max:999999999999',
            'categoryId' => 'required|integer',
            'date' => 'required|date',
            'description' => 'nullable|string|max:255',
        ], [
            'amount.required' => 'Nominal wajib diisi.',
            'amount.min' => 'Nominal harus lebih dari 0.',
            'amount.max' => 'Nominal terlalu besar.',
            'categoryId.required' => 'Pilih kategori dulu.',
            'date.required' => 'Tanggal wajib diisi.',
            'date.date' => 'Tanggal tidak valid.',
            'description.max' => 'Catatan maksimal 255 karakter.',
        ]);

        $category = Category::where('household_id', $user->household_id)
            ->where('type', $data['type'])
            ->find((int) $data['categoryId']);

        if (! $category) {
            $this->addError('categoryId', 'Kategori tidak cocok dengan tipe transaksi.');
            return;
        }

        $attrs = [
            'type' => $data['type'],
            'amount' => (int) $data['amount'],
            'category_id' => $category->id,
            'date' => $data['date'],
            'description' => trim($data['description'] ?? '') ?: null,
        ];

        if ($this->editingId) {
            $t = Transaction::where('household_id', $user->household_id)->findOrFail($this->editingId);
            $t->update($attrs);
            $action = 'updated';
        } else {
            $t = Transaction::create($attrs + ['household_id' => $user->household_id, 'user_id' => $user->id]);
            $action = 'created';
        }

        $notifier->transaction($user, $action, $t->load('category'));

        $this->show = false;
        $this->dispatch('transaction-saved');
        $this->dispatch('toast', message: $action === 'created' ? 'Transaksi dicatat' : 'Transaksi diperbarui');
    }

    public function delete(PartnerNotifier $notifier): void
    {
        if ($this->denyDemo()) {
            return;
        }

        if (! $this->editingId) {
            return;
        }

        $user = auth()->user();
        $t = Transaction::with('category')->where('household_id', $user->household_id)->findOrFail($this->editingId);
        $t->delete();

        $notifier->transaction($user, 'deleted', $t);

        $this->show = false;
        $this->dispatch('transaction-saved');
        $this->dispatch('toast', message: 'Transaksi dihapus');
    }

    public function render()
    {
        $categories = $this->show
            ? Category::where('household_id', auth()->user()->household_id)->where('type', $this->type)->orderBy('name')->get()
            : collect();

        return view('livewire.transaction-form', ['categories' => $categories]);
    }
}
