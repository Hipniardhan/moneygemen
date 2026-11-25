<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('components.layouts.guest')]
class Login extends Component
{
    #[Validate('required|email', message: ['required' => 'Email wajib diisi.', 'email' => 'Format email tidak valid.'])]
    public string $email = '';

    #[Validate('required', message: ['required' => 'Kata sandi wajib diisi.'])]
    public string $password = '';

    public bool $remember = true;

    public function login()
    {
        $this->validate();

        $key = 'login|' . Str::lower($this->email) . '|' . request()->ip();
        // batas per IP: menahan penebakan massal ke banyak email dari satu sumber
        $ipKey = 'login-ip|' . request()->ip();

        foreach ([[$key, 5], [$ipKey, 20]] as [$k, $max]) {
            if (RateLimiter::tooManyAttempts($k, $max)) {
                $this->addError('email', 'Terlalu banyak percobaan. Coba lagi dalam ' . RateLimiter::availableIn($k) . ' detik.');
                return;
            }
        }

        if (! Auth::attempt(['email' => Str::lower($this->email), 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($key, 60);
            RateLimiter::hit($ipKey, 300);
            $this->addError('email', 'Email atau kata sandi salah.');
            return;
        }

        RateLimiter::clear($key);
        session()->regenerate();

        return $this->redirectIntended('/', navigate: false);
    }

    public function render()
    {
        return view('livewire.auth.login')->title('Masuk');
    }
}
