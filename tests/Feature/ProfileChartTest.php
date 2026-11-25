<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Livewire\Profile;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\HouseholdService;
use App\Services\Rupiah;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileChartTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name = 'Budi'): User
    {
        return app(HouseholdService::class)->register($name, strtolower($name) . '@mail.test', 'password123');
    }

    private function tx(User $u, string $cat, int $amount, string $type, ?string $date = null): void
    {
        Transaction::create([
            'household_id' => $u->household_id, 'user_id' => $u->id,
            'category_id' => Category::where('household_id', $u->household_id)->where('name', $cat)->value('id'),
            'type' => $type, 'amount' => $amount, 'date' => $date ?? now()->toDateString(),
        ]);
    }

    public function test_compact_rupiah_labels(): void
    {
        $this->assertSame('0', Rupiah::compact(0));
        $this->assertSame('950', Rupiah::compact(950));
        $this->assertSame('450rb', Rupiah::compact(450000));
        $this->assertSame('8,5jt', Rupiah::compact(8500000));
        $this->assertSame('8jt', Rupiah::compact(8000000));
        $this->assertSame('1,2M', Rupiah::compact(1200000000));
    }

    public function test_dashboard_chart_groups_by_category_and_type(): void
    {
        $u = $this->user();
        $this->tx($u, 'Gaji & Upah', 8500000, 'income');
        $this->tx($u, 'Tagihan', 300000, 'expense');
        $this->tx($u, 'Tagihan', 150000, 'expense');
        $this->tx($u, 'Hiburan', 50000, 'expense');
        $this->tx($u, 'Tagihan', 999000, 'expense', now()->subMonths(2)->toDateString()); // di luar "bulan ini"

        $c = Livewire::actingAs($u)->test(Dashboard::class)->set('period', 'month');
        $chart = $c->viewData('chart');

        $this->assertCount(1, $chart['income']);
        $this->assertSame(8500000, (int) $chart['income']->first()->total);
        $this->assertSame(['Tagihan', 'Hiburan'], $chart['expense']->pluck('name')->all());
        $this->assertSame(450000, (int) $chart['expense']->first()->total);
        $c->assertSee('8,5jt')->assertSee('450rb');

        // periode "Semua" ikut menghitung transaksi lama
        $all = Livewire::actingAs($u)->test(Dashboard::class)->set('period', 'all')->viewData('chart');
        $this->assertSame(1449000, (int) $all['expense']->first()->total);
    }

    public function test_dashboard_chart_limits_to_top_five_and_handles_empty(): void
    {
        $u = $this->user();
        Livewire::actingAs($u)->test(Dashboard::class)->assertSee('Belum ada transaksi');

        foreach (['Tagihan', 'Hiburan', 'Belanja', 'Kesehatan', 'Pendidikan', 'Makanan & Minuman'] as $i => $cat) {
            $this->tx($u, $cat, ($i + 1) * 1000, 'expense');
        }
        $chart = Livewire::actingAs($u)->test(Dashboard::class)->viewData('chart');
        $this->assertCount(5, $chart['expense']);
        $this->assertSame(6000, (int) $chart['expense']->first()->total);
    }

    public function test_dashboard_chart_is_scoped_to_household(): void
    {
        $a = $this->user('Rina');
        $b = $this->user('Budi');
        $this->tx($b, 'Tagihan', 777000, 'expense');

        $chart = Livewire::actingAs($a)->test(Dashboard::class)->viewData('chart');
        $this->assertTrue($chart['expense']->isEmpty());
    }

    public function test_profile_page_contains_partner_section(): void
    {
        $u = $this->user();
        $this->actingAs($u)->get('/profil')->assertOk()
            ->assertSee('Undang pasangan')->assertSee('Ganti kata sandi')->assertSee('Keluar')->assertSee('budi@mail.test');
    }

    public function test_update_name(): void
    {
        $u = $this->user();
        Livewire::actingAs($u)->test(Profile::class)->set('name', '  Budi Santoso ')->call('saveName')->assertHasNoErrors();
        $this->assertSame('Budi Santoso', $u->fresh()->name);

        Livewire::actingAs($u)->test(Profile::class)->set('name', '')->call('saveName')->assertHasErrors('name');
        Livewire::actingAs($u)->test(Profile::class)->set('name', str_repeat('a', 51))->call('saveName')->assertHasErrors('name');
    }

    public function test_change_password_requires_current_and_min_length(): void
    {
        $u = $this->user();

        $c = Livewire::actingAs($u)->test(Profile::class)
            ->set('currentPassword', 'salah-salah')->set('newPassword', 'barubaru123')->set('newPasswordConfirmation', 'barubaru123')
            ->call('savePassword')->assertHasErrors('currentPassword');
        $this->assertTrue(Hash::check('password123', $u->fresh()->password));

        $c->set('currentPassword', 'password123')->set('newPassword', 'pendek')->set('newPasswordConfirmation', 'pendek')
            ->call('savePassword')->assertHasErrors('newPassword');

        $c->set('newPassword', 'barubaru123')->set('newPasswordConfirmation', 'beda-sekali')
            ->call('savePassword')->assertHasErrors('newPassword');

        $c->set('newPassword', 'barubaru123')->set('newPasswordConfirmation', 'barubaru123')
            ->call('savePassword')->assertHasNoErrors();
        $this->assertTrue(Hash::check('barubaru123', $u->fresh()->password));
    }

    public function test_change_password_is_rate_limited(): void
    {
        $u = $this->user();
        $c = Livewire::actingAs($u)->test(Profile::class)
            ->set('newPassword', 'barubaru123')->set('newPasswordConfirmation', 'barubaru123');

        for ($i = 0; $i < 5; $i++) {
            $c->set('currentPassword', 'salah-salah')->call('savePassword');
        }
        // percobaan ke-6 diblokir meski sandi lama benar
        $c->set('currentPassword', 'password123')->call('savePassword')->assertHasErrors('currentPassword');
        $this->assertTrue(Hash::check('password123', $u->fresh()->password));
    }

    public function test_demo_user_cannot_change_password(): void
    {
        $demo = app(HouseholdService::class)->demoUser();
        $old = $demo->password;

        Livewire::actingAs($demo)->test(Profile::class)
            ->set('currentPassword', 'x')->set('newPassword', 'barubaru123')->set('newPasswordConfirmation', 'barubaru123')
            ->call('savePassword');
        $this->assertSame($old, $demo->fresh()->password);
    }
}
