<?php

namespace App\Livewire\Concerns;

/** Akun demo hanya boleh melihat-lihat. Pakai di awal setiap aksi yang menulis data. */
trait BlocksDemo
{
    protected function denyDemo(): bool
    {
        if (! auth()->user()?->is_demo) {
            return false;
        }

        $this->dispatch('toast', message: 'Akun demo hanya untuk melihat-lihat. Daftar gratis untuk mencatat.', error: true);

        return true;
    }
}
