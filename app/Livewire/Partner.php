<?php

namespace App\Livewire;

use App\Models\PartnerInvite;
use App\Services\HouseholdService;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;
use RuntimeException;

class Partner extends Component
{
    public string $code = '';
    public bool $confirmDisconnect = false;

    public function createInvite(HouseholdService $households): void
    {
        try {
            $households->createInvite(auth()->user());
        } catch (RuntimeException $e) {
            $this->addError('invite', $e->getMessage());
        }
    }

    public function accept(HouseholdService $households)
    {
        $user = auth()->user();
        $key = 'invite-accept|' . $user->id;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('code', 'Terlalu banyak percobaan. Coba lagi dalam ' . RateLimiter::availableIn($key) . ' detik.');
            return;
        }

        $this->code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $this->code));
        $this->validate(
            ['code' => 'required|size:8'],
            ['code.required' => 'Masukkan kode undangan.', 'code.size' => 'Kode terdiri dari 8 karakter.']
        );

        try {
            $households->acceptInvite($user, $this->code);
        } catch (RuntimeException $e) {
            RateLimiter::hit($key, 300);
            $this->addError('code', $e->getMessage());
            return;
        }

        RateLimiter::clear($key);
        session()->flash('toast', 'Berhasil terhubung! Catatan kalian sekarang digabung.');

        return $this->redirect(route('profile'));
    }

    public function disconnect(HouseholdService $households)
    {
        try {
            $households->disconnect(auth()->user());
        } catch (RuntimeException $e) {
            $this->addError('invite', $e->getMessage());
            return;
        }

        session()->flash('toast', 'Hubungan diputus. Catatanmu tetap tersimpan.');

        return $this->redirect(route('profile'));
    }

    public function render()
    {
        $user = auth()->user();
        $partner = $user->partner();

        $invite = null;
        if (! $partner) {
            $invite = PartnerInvite::where('created_by', $user->id)
                ->whereNull('used_at')->where('expires_at', '>', now())
                ->latest('id')->first();
        }

        return view('livewire.partner', [
            'partner' => $partner,
            'invite' => $invite,
        ]);
    }
}
