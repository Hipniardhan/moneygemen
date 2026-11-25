<?php

namespace App\Livewire;

use App\Livewire\Concerns\BlocksDemo;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Profile extends Component
{
    use BlocksDemo;

    public string $name = '';

    public string $currentPassword = '';
    public string $newPassword = '';
    public string $newPasswordConfirmation = '';

    public function mount(): void
    {
        $this->name = auth()->user()->name;
    }

    public function saveName(): void
    {
        if ($this->denyDemo()) {
            return;
        }

        $this->name = trim($this->name);

        $this->validate(
            ['name' => 'required|string|max:50'],
            ['name.required' => 'Nama wajib diisi.', 'name.max' => 'Nama maksimal 50 karakter.']
        );

        auth()->user()->update(['name' => $this->name]);

        $this->dispatch('toast', message: 'Nama diperbarui');
    }

    public function savePassword(): void
    {
        $user = auth()->user();

        if ($user->is_demo) {
            return;
        }

        $key = 'change-password|' . $user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('currentPassword', 'Terlalu banyak percobaan. Coba lagi dalam ' . RateLimiter::availableIn($key) . ' detik.');
            return;
        }

        $this->validate([
            'currentPassword' => 'required',
            'newPassword' => 'required|string|min:8|max:100|same:newPasswordConfirmation|different:currentPassword',
            'newPasswordConfirmation' => 'required',
        ], [
            'currentPassword.required' => 'Masukkan kata sandi saat ini.',
            'newPassword.required' => 'Kata sandi baru wajib diisi.',
            'newPassword.min' => 'Kata sandi baru minimal 8 karakter.',
            'newPassword.same' => 'Konfirmasi kata sandi tidak cocok.',
            'newPassword.different' => 'Kata sandi baru harus berbeda dari yang lama.',
            'newPasswordConfirmation.required' => 'Ulangi kata sandi baru.',
        ]);

        if (! Hash::check($this->currentPassword, $user->password)) {
            RateLimiter::hit($key, 300);
            $this->addError('currentPassword', 'Kata sandi saat ini salah.');
            return;
        }

        RateLimiter::clear($key);
        $user->update(['password' => $this->newPassword]);
        $this->reset(['currentPassword', 'newPassword', 'newPasswordConfirmation']);

        $this->dispatch('toast', message: 'Kata sandi diganti');
    }

    public function render()
    {
        return view('livewire.profile', ['user' => auth()->user()])->title('Profil');
    }
}
