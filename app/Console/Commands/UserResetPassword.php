<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class UserResetPassword extends Command
{
    protected $signature = 'user:reset-password {email} {password? : Kosongkan untuk membuat sandi acak}';

    protected $description = 'Reset kata sandi user (untuk admin lewat Terminal)';

    public function handle(): int
    {
        $user = User::where('email', mb_strtolower($this->argument('email')))->first();
        if (! $user) {
            $this->error('User tidak ditemukan.');
            return self::FAILURE;
        }

        $password = $this->argument('password') ?: Str::random(12);
        if (mb_strlen($password) < 8) {
            $this->error('Kata sandi minimal 8 karakter.');
            return self::FAILURE;
        }

        $user->update(['password' => $password]);
        $this->info("Kata sandi {$user->email} diganti. Sandi baru: {$password}");
        return self::SUCCESS;
    }
}
