<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Household;
use App\Models\PartnerInvite;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class HouseholdService
{
    public function __construct(private PartnerNotifier $notifier) {}

    /** Buat user + household + 12 kategori bawaan. */
    public function register(string $name, string $email, string $password, bool $demo = false): User
    {
        return DB::transaction(function () use ($name, $email, $password, $demo) {
            $household = Household::create(['name' => 'Dompet ' . $name]);

            foreach (Category::defaults() as [$n, $type, $color, $icon]) {
                $household->categories()->create(['name' => $n, 'type' => $type, 'color' => $color, 'icon' => $icon]);
            }

            return User::create([
                'household_id' => $household->id,
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'is_demo' => $demo,
            ]);
        });
    }

    public const DEMO_EMAIL = 'demo@demo.moneygemen';

    /**
     * Satu akun demo bersama yang HANYA-BACA (penulisan ditolak di tiap komponen).
     * Tidak membuat akun baru per kunjungan; data contoh disegarkan tiap hari
     * supaya tanggalnya tetap relevan.
     */
    public function demoUser(): User
    {
        $user = User::where('email', self::DEMO_EMAIL)->first();

        if (! $user) {
            try {
                $user = $this->register('Demo', self::DEMO_EMAIL, Str::random(40), true);
            } catch (\Illuminate\Database\QueryException $e) {
                // dua pengunjung pertama bersamaan: yang kalah memakai akun yang sudah dibuat
                $user = User::where('email', self::DEMO_EMAIL)->firstOrFail();
            }
        }

        // jangan pernah memasukkan pengunjung ke akun asli yang kebetulan memakai email demo
        if (! $user->is_demo) {
            throw new RuntimeException('Akun demo tidak tersedia.');
        }

        $last = Transaction::where('household_id', $user->household_id)->max('date');
        if (! $last || Carbon::parse($last)->lt(today())) {
            DB::transaction(function () use ($user) {
                User::whereKey($user->id)->lockForUpdate()->first(); // serialisasi pengisian ulang
                $last = Transaction::where('household_id', $user->household_id)->max('date');
                if (! $last || Carbon::parse($last)->lt(today())) {
                    $this->seedDemo($user);
                }
            });
        }

        return $user;
    }

    private function seedDemo(User $user): void
    {
        Transaction::where('household_id', $user->household_id)->delete();
        $cats = $user->household->categories()->get()->keyBy('name');

        $rows = [
            [0, 'Gaji & Upah', 'income', 8500000, 'Gaji bulanan'],
            [3, 'Bonus & THR', 'income', 1500000, 'Bonus proyek'],
            [1, 'Tagihan', 'expense', 450000, 'Listrik & internet'],
            [1, 'Makanan & Minuman', 'expense', 85000, 'Makan siang tim'],
            [2, 'Transportasi & Bensin', 'expense', 120000, 'Bensin motor'],
            [3, 'Belanja', 'expense', 640000, 'Belanja bulanan'],
            [4, 'Makanan & Minuman', 'expense', 50000, 'Kopi & roti'],
            [5, 'Kesehatan', 'expense', 230000, 'Vitamin & obat'],
            [6, 'Hiburan', 'expense', 150000, 'Nonton bioskop'],
            [7, 'Makanan & Minuman', 'expense', 175000, 'Makan malam keluarga'],
        ];
        foreach ($rows as [$ago, $cat, $type, $amount, $desc]) {
            Transaction::create([
                'household_id' => $user->household_id,
                'user_id' => $user->id,
                'category_id' => $cats[$cat]->id,
                'type' => $type,
                'amount' => $amount,
                'date' => now()->subDays($ago)->toDateString(),
                'description' => $desc,
            ]);
        }
    }

    public function createInvite(User $user): PartnerInvite
    {
        if ($user->is_demo) {
            throw new RuntimeException('Akun demo tidak bisa terhubung dengan pasangan.');
        }
        if ($user->partner()) {
            throw new RuntimeException('Kamu sudah terhubung dengan pasangan.');
        }

        // satu kode aktif per user: kode lama yang belum terpakai dibatalkan
        PartnerInvite::where('created_by', $user->id)->whereNull('used_at')->delete();

        do {
            // huruf/angka yang mudah dibedakan (tanpa O/0, I/1/L)
            $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (PartnerInvite::where('code', $code)->exists());

        return PartnerInvite::create([
            'household_id' => $user->household_id,
            'created_by' => $user->id,
            'code' => $code,
            'expires_at' => now()->addHours(24),
        ]);
    }

    /** Gabungkan household penerima ke household pembuat kode. */
    public function acceptInvite(User $me, string $code): void
    {
        $code = strtoupper(trim($code));

        DB::transaction(function () use ($me, $code) {
            $invite = PartnerInvite::where('code', $code)->lockForUpdate()->first();

            if (! $invite || ! $invite->isActive()) {
                throw new RuntimeException('Kode tidak valid atau sudah kedaluwarsa.');
            }

            $inviter = User::lockForUpdate()->find($invite->created_by);
            if (! $inviter || $inviter->id === $me->id) {
                throw new RuntimeException('Kode tidak valid. Kamu tidak bisa memakai kodemu sendiri.');
            }
            if ($inviter->is_demo || $me->is_demo) {
                throw new RuntimeException('Akun demo tidak bisa terhubung dengan pasangan.');
            }
            if ($me->partner() || $inviter->partner()) {
                throw new RuntimeException('Salah satu akun sudah terhubung dengan pasangan lain.');
            }

            $from = $me->household_id;
            $to = $inviter->household_id;

            $targetCats = Category::where('household_id', $to)->get()
                ->keyBy(fn ($c) => $c->type . '|' . mb_strtolower($c->name));

            foreach (Category::where('household_id', $from)->get() as $cat) {
                $key = $cat->type . '|' . mb_strtolower($cat->name);
                if ($existing = $targetCats->get($key)) {
                    Transaction::where('category_id', $cat->id)->update(['category_id' => $existing->id]);
                    $cat->delete();
                } else {
                    $cat->update(['household_id' => $to]);
                }
            }

            Transaction::where('household_id', $from)->update(['household_id' => $to]);
            $me->update(['household_id' => $to]);
            Household::where('id', $from)->delete();

            $invite->update(['used_at' => now(), 'used_by' => $me->id]);

            $this->notifier->system($inviter, $me, 'partner_connected', "{$me->name} terhubung sebagai pasanganmu.");
        });
    }

    /** Putuskan: yang memutuskan membawa transaksi yang ia catat sendiri. */
    public function disconnect(User $me): void
    {
        DB::transaction(function () use ($me) {
            $partner = $me->partner();
            if (! $partner) {
                throw new RuntimeException('Kamu belum terhubung dengan pasangan.');
            }

            $old = $me->household_id;
            $new = Household::create(['name' => 'Dompet ' . $me->name]);

            $map = [];
            foreach (Category::where('household_id', $old)->get() as $cat) {
                $copy = $new->categories()->create($cat->only(['name', 'type', 'color', 'icon']));
                $map[$cat->id] = $copy->id;
            }

            foreach (Transaction::where('household_id', $old)->where('user_id', $me->id)->get() as $t) {
                $t->update(['household_id' => $new->id, 'category_id' => $map[$t->category_id]]);
            }

            $me->update(['household_id' => $new->id]);

            $this->notifier->system($partner, $me, 'partner_disconnected', "{$me->name} memutuskan hubungan. Catatanmu tetap tersimpan.");
        });
    }
}
