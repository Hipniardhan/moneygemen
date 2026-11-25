<?php

namespace App\Livewire;

use App\Livewire\Concerns\BlocksDemo;
use App\Models\Category;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Categories extends Component
{
    use BlocksDemo;

    public string $tab = 'expense';

    public bool $showForm = false;
    public ?int $editingId = null;
    public string $name = '';
    public string $type = 'expense';
    public string $color = '#0f766e';
    public string $icon = '🏷️';

    public function setTab(string $tab): void
    {
        $this->tab = $tab === 'income' ? 'income' : 'expense';
    }

    public function create(): void
    {
        if ($this->denyDemo()) {
            return;
        }

        $this->resetValidation();
        $this->reset(['editingId', 'name']);
        $this->type = $this->tab;
        $this->color = Category::COLORS[0];
        $this->icon = Category::ICONS[0];
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        if ($this->denyDemo()) {
            return;
        }

        $c =Category::where('household_id', auth()->user()->household_id)->findOrFail($id);
        $this->resetValidation();
        $this->editingId = $c->id;
        $this->name = $c->name;
        $this->type = $c->type;
        $this->color = $c->color;
        $this->icon = $c->icon;
        $this->showForm = true;
    }

    public function close(): void
    {
        $this->showForm = false;
    }

    public function save(): void
    {
        if ($this->denyDemo()) {
            return;
        }

        $hid =auth()->user()->household_id;
        $this->name = trim($this->name);
        $this->icon = trim($this->icon);
        $this->color = strtolower(trim($this->color));

        $this->validate([
            'name' => [
                'required', 'max:60',
                Rule::unique('categories', 'name')
                    ->where('household_id', $hid)->where('type', $this->type)->ignore($this->editingId),
            ],
            'type' => 'required|in:income,expense',
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'icon' => ['required', fn ($attr, $value, $fail) => Category::isValidIcon((string) $value) || $fail('Isi ikon dengan satu emoji.')],
        ], [
            'color.required' => 'Pilih warna dulu.',
            'color.regex' => 'Warna tidak valid.',
            'icon.required' => 'Pilih atau ketik satu emoji.',
            'name.required' => 'Nama kategori wajib diisi.',
            'name.max' => 'Nama maksimal 60 karakter.',
            'name.unique' => 'Kategori dengan nama ini sudah ada.',
        ]);

        if ($this->editingId) {
            $c = Category::where('household_id', $hid)->findOrFail($this->editingId);
            // tipe tidak boleh diubah jika sudah dipakai transaksi
            $type = $c->transactions()->exists() ? $c->type : $this->type;
            $c->update(['name' => $this->name, 'type' => $type, 'color' => $this->color, 'icon' => $this->icon]);
        } else {
            Category::create([
                'household_id' => $hid, 'name' => $this->name, 'type' => $this->type,
                'color' => $this->color, 'icon' => $this->icon,
            ]);
            $this->tab = $this->type;
        }

        $this->showForm = false;
        $this->dispatch('toast', message: 'Kategori disimpan');
    }

    public function delete(int $id): void
    {
        if ($this->denyDemo()) {
            return;
        }

        $c =Category::where('household_id', auth()->user()->household_id)->findOrFail($id);

        if ($c->transactions()->exists()) {
            $this->dispatch('toast', message: 'Kategori masih dipakai transaksi, tidak bisa dihapus', error: true);
            return;
        }

        $c->delete();
        $this->showForm = false;
        $this->dispatch('toast', message: 'Kategori dihapus');
    }

    public function render()
    {
        $hid = auth()->user()->household_id;

        $categories = Category::withCount('transactions')
            ->where('household_id', $hid)->where('type', $this->tab)
            ->orderBy('name')->get();

        return view('livewire.categories', [
            'categories' => $categories,
            'colors' => Category::COLORS,
            'icons' => Category::ICONS,
        ])->title('Kategori');
    }
}
