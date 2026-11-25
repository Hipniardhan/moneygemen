<?php

namespace App\Livewire\Auth;

use App\Services\HouseholdService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.guest')]
class Register extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';

    public function register(HouseholdService $households)
    {
        $key = 'register|' . request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('email', 'Terlalu banyak percobaan daftar. Coba lagi dalam ' . RateLimiter::availableIn($key) . ' detik.');
            return;
        }
        RateLimiter::hit($key, 600);

        $this->email = Str::lower(trim($this->email));

        $this->validate([
            'name' => 'required|string|max:50',
            // domain demo.moneygemen dicadangkan untuk akun demo bersama
            'email' => ['required', 'email', 'max:190', 'unique:users,email', 'not_regex:/@demo\.moneygemen$/i'],
            'password' => 'required|string|min:8|max:100',
        ], [
            'name.required' => 'Nama wajib diisi.',
            'name.max' => 'Nama maksimal 50 karakter.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email ini sudah terdaftar.',
            'email.not_regex' => 'Email ini tidak bisa dipakai.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
        ]);

        $user = $households->register(trim($this->name), $this->email, $this->password);

        Auth::login($user, true);
        session()->regenerate();

        return $this->redirect('/');
    }

    public function render()
    {
        return view('livewire.auth.register')->title('Daftar');
    }
}
