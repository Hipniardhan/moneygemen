<?php

namespace App\Console\Commands;

use App\Models\Household;
use App\Models\PartnerInvite;
use App\Models\PartnerNotification;
use App\Models\User;
use App\Services\HouseholdService;
use Illuminate\Console\Command;

class Cleanup extends Command
{
    protected $signature = 'moneygemen:cleanup';

    protected $description = 'Hapus notifikasi >90 hari, kode undangan lama, dan akun demo >1 hari';

    public function handle(): int
    {
        $n = PartnerNotification::where('created_at', '<', now()->subDays(90))->delete();
        $i = PartnerInvite::where('expires_at', '<', now()->subDays(7))->delete();

        // akun demo bersama (DEMO_EMAIL) dipertahankan; hanya sisa akun demo versi lama yang dihapus
        $demoHouseholds = User::where('is_demo', true)->where('email', '!=', HouseholdService::DEMO_EMAIL)
            ->where('created_at', '<', now()->subDay())->pluck('household_id');
        $d = Household::whereIn('id', $demoHouseholds)->delete(); // cascade ke user, kategori, transaksi

        $this->info("Notifikasi: $n, undangan: $i, akun demo: $d dihapus.");
        return self::SUCCESS;
    }
}
