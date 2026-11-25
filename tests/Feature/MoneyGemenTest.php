<?php

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Categories;
use App\Livewire\Dashboard;
use App\Livewire\Partner;
use App\Livewire\TransactionForm;
use App\Livewire\Transactions;
use App\Models\Category;
use App\Models\PartnerNotification;
use App\Models\Transaction;
use App\Models\User;
use App\Services\HouseholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MoneyGemenTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name = 'Budi'): User
    {
        return app(HouseholdService::class)->register($name, strtolower($name) . '@mail.test', 'password123');
    }

    private function cat(User $u, string $name): Category
    {
        return Category::where('household_id', $u->household_id)->where('name', $name)->firstOrFail();
    }

    private function tx(User $u, string $catName, int $amount, string $type = 'expense'): Transaction
    {
        return Transaction::create([
            'household_id' => $u->household_id, 'user_id' => $u->id, 'category_id' => $this->cat($u, $catName)->id,
            'type' => $type, 'amount' => $amount, 'date' => now()->toDateString(),
        ]);
    }

    private function pair(): array
    {
        $a = $this->user('Rina');
        $b = $this->user('Budi');
        $invite = app(HouseholdService::class)->createInvite($a);
        app(HouseholdService::class)->acceptInvite($b, $invite->code);

        return [$a->fresh(), $b->fresh()];
    }

    public function test_register_creates_12_default_categories(): void
    {
        Livewire::test(Register::class)
            ->set('name', 'Sari')->set('email', 'SARI@mail.test')->set('password', 'rahasia123')
            ->call('register')->assertRedirect('/');

        $u = User::where('email', 'sari@mail.test')->firstOrFail();
        $this->assertSame(12, Category::where('household_id', $u->household_id)->count());
        $this->assertAuthenticatedAs($u);
    }

    public function test_register_rejects_short_password(): void
    {
        Livewire::test(Register::class)
            ->set('name', 'Sari')->set('email', 'sari@mail.test')->set('password', 'short')
            ->call('register')->assertHasErrors(['password']);
    }

    public function test_login_is_rate_limited(): void
    {
        $this->user('Budi');
        $c = Livewire::test(Login::class)->set('email', 'budi@mail.test')->set('password', 'salah-salah');
        for ($i = 0; $i < 5; $i++) {
            $c->call('login');
        }
        // percobaan ke-6 diblokir meski sandi benar
        $c->set('password', 'password123')->call('login')->assertHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_success(): void
    {
        $this->user('Budi');
        Livewire::test(Login::class)->set('email', 'budi@mail.test')->set('password', 'password123')
            ->call('login')->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_demo_account_has_data_and_pages_render(): void
    {
        $this->get('/demo')->assertRedirect('/');
        $this->get('/')->assertOk()->assertSee('Saldo');
        $this->get('/transaksi')->assertOk();
        $this->get('/kategori')->assertOk();
        $this->get('/profil')->assertOk();
        $this->get('/pasangan')->assertRedirect('/profil');
        $this->assertGreaterThan(0, Transaction::count());
    }

    public function test_guest_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/masuk');
        $this->get('/masuk')->assertOk();
        $this->get('/offline')->assertOk();
    }

    public function test_create_edit_delete_transaction_with_formatted_amount(): void
    {
        $u = $this->user();
        $cat = $this->cat($u, 'Makanan & Minuman');

        $c = Livewire::actingAs($u)->test(TransactionForm::class)
            ->call('open')->set('amount', '1.250.000')->set('categoryId', (string) $cat->id)->set('description', 'Makan')
            ->call('save')->assertHasNoErrors();

        $t = Transaction::firstOrFail();
        $this->assertSame(1250000, $t->amount);

        $c->call('open', $t->id)->set('amount', '50000')->call('save');
        $this->assertSame(50000, $t->fresh()->amount);

        $c->call('open', $t->id)->call('delete');
        $this->assertSame(0, Transaction::count());
    }

    public function test_cannot_use_other_households_category_or_transaction(): void
    {
        $a = $this->user('Rina');
        $b = $this->user('Budi');
        $foreign = $this->cat($b, 'Makanan & Minuman');
        $tx = $this->tx($b, 'Makanan & Minuman', 1000);

        Livewire::actingAs($a)->test(TransactionForm::class)
            ->call('open')->set('amount', '1000')->set('categoryId', (string) $foreign->id)
            ->call('save')->assertHasErrors('categoryId');

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        Livewire::actingAs($a)->test(TransactionForm::class)->call('open', $tx->id);
    }

    public function test_category_type_must_match_transaction_type(): void
    {
        $u = $this->user();
        Livewire::actingAs($u)->test(TransactionForm::class)
            ->call('open')->set('type', 'expense')->set('amount', '1000')
            ->set('categoryId', (string) $this->cat($u, 'Gaji & Upah')->id)
            ->call('save')->assertHasErrors('categoryId');
    }

    public function test_used_category_cannot_be_deleted_but_unused_can(): void
    {
        $u = $this->user();
        $used = $this->cat($u, 'Tagihan');
        $this->tx($u, 'Tagihan', 1000);
        $free = $this->cat($u, 'Hiburan');

        $c = Livewire::actingAs($u)->test(Categories::class);
        $c->call('delete', $used->id);
        $this->assertNotNull(Category::find($used->id));
        $c->call('delete', $free->id);
        $this->assertNull(Category::find($free->id));
    }

    public function test_category_crud(): void
    {
        $u = $this->user();
        Livewire::actingAs($u)->test(Categories::class)
            ->call('create')->set('name', 'Langganan')->set('color', Category::COLORS[3])->set('icon', Category::ICONS[2])
            ->call('save')->assertHasNoErrors();
        $this->assertSame(13, Category::where('household_id', $u->household_id)->count());

        Livewire::actingAs($u)->test(Categories::class)
            ->call('create')->set('name', 'Langganan')->call('save')->assertHasErrors('name');
    }

    public function test_dashboard_totals_and_top_categories(): void
    {
        $u = $this->user();
        $this->tx($u, 'Gaji & Upah', 5000000, 'income');
        $this->tx($u, 'Tagihan', 700000);
        $this->tx($u, 'Makanan & Minuman', 300000);

        Livewire::actingAs($u)->test(Dashboard::class)
            ->assertSee('Rp 5.000.000')->assertSee('Rp 1.000.000')->assertSee('Rp 4.000.000')
            ->assertSeeInOrder(['Tagihan', 'Makanan & Minuman']);
    }

    public function test_transactions_filter_search_sort_and_load_more(): void
    {
        $u = $this->user();
        for ($i = 1; $i <= 25; $i++) {
            $this->tx($u, 'Belanja', $i * 1000);
        }
        $this->tx($u, 'Gaji & Upah', 9, 'income');

        $c = Livewire::actingAs($u)->test(Transactions::class);
        $this->assertCount(20, $c->viewData('items'));
        $this->assertTrue($c->viewData('hasMore'));
        $c->call('more');
        $this->assertCount(26, $c->viewData('items'));

        $c->set('type', 'income');
        $this->assertCount(1, $c->viewData('items'));

        $c->set('type', 'all')->set('sort', 'largest');
        $this->assertSame(25000, $c->viewData('items')->first()->amount);

        $c->set('search', 'gaji');
        $this->assertCount(1, $c->viewData('items'));
    }

    public function test_invite_merges_households_and_unifies_categories(): void
    {
        $a = $this->user('Rina');
        $b = $this->user('Budi');
        $custom = Category::create(['household_id' => $b->household_id, 'name' => 'Kucing', 'type' => 'expense', 'color' => '#dc2626', 'icon' => '🐶']);
        $this->tx($a, 'Tagihan', 100);
        $this->tx($b, 'Tagihan', 200);
        $this->tx($b, 'Kucing', 300);
        $bOld = $b->household_id;

        $invite = app(HouseholdService::class)->createInvite($a);
        $this->assertSame(8, strlen($invite->code));
        app(HouseholdService::class)->acceptInvite($b, $invite->code);

        $a->refresh(); $b->refresh();
        $this->assertSame($a->household_id, $b->household_id);
        $this->assertDatabaseMissing('households', ['id' => $bOld]);
        $this->assertSame(3, Transaction::where('household_id', $a->household_id)->count());
        // "Tagihan" menyatu, "Kucing" ikut pindah → 12 + 1
        $this->assertSame(13, Category::where('household_id', $a->household_id)->count());
        $this->assertSame(1, Category::where('household_id', $a->household_id)->where('name', 'Tagihan')->count());
        $this->assertSame($a->id, $b->partner()->id);
    }

    public function test_invite_is_single_use_and_expires(): void
    {
        $a = $this->user('Rina'); $b = $this->user('Budi'); $c = $this->user('Sari');
        $svc = app(HouseholdService::class);

        $expired = $svc->createInvite($c);
        $expired->update(['expires_at' => now()->subMinute()]);
        try { $svc->acceptInvite($b, $expired->code); $this->fail('harus gagal'); } catch (\RuntimeException $e) { $this->assertStringContainsString('kedaluwarsa', $e->getMessage()); }

        $invite = $svc->createInvite($a);
        $svc->acceptInvite($b, $invite->code);
        try { $svc->acceptInvite($c, $invite->code); $this->fail('harus gagal'); } catch (\RuntimeException) { $this->assertTrue(true); }
    }

    public function test_cannot_accept_own_code_or_when_already_paired(): void
    {
        [$a, $b] = $this->pair();
        $c = $this->user('Sari');
        $svc = app(HouseholdService::class);

        $this->expectException(\RuntimeException::class);
        $svc->createInvite($a); // sudah punya pasangan
    }

    public function test_third_user_cannot_join_paired_account(): void
    {
        [$a, $b] = $this->pair();
        $c = $this->user('Sari');
        $d = $this->user('Dewi');
        $svc = app(HouseholdService::class);

        $invite = $svc->createInvite($d);
        $this->expectException(\RuntimeException::class);
        $svc->acceptInvite($a, $invite->code); // a sudah berpasangan
    }

    public function test_disconnect_splits_data_fairly(): void
    {
        [$a, $b] = $this->pair();
        $this->tx($a, 'Tagihan', 100);
        $this->tx($b, 'Tagihan', 200);
        $this->tx($b, 'Gaji & Upah', 900, 'income');

        app(HouseholdService::class)->disconnect($b);
        $a->refresh(); $b->refresh();

        $this->assertNotSame($a->household_id, $b->household_id);
        $this->assertSame(1, Transaction::where('household_id', $a->household_id)->count());
        $this->assertSame(2, Transaction::where('household_id', $b->household_id)->count());
        $this->assertSame(12, Category::where('household_id', $b->household_id)->count());
        // kategori transaksi mengikuti household masing-masing
        foreach (Transaction::with('category')->get() as $t) {
            $this->assertSame($t->household_id, $t->category->household_id);
        }
        $this->assertNull($a->partner());
        $this->assertNull($b->partner());
    }

    public function test_partner_gets_notified_on_create_update_delete(): void
    {
        [$a, $b] = $this->pair();
        $cat = $this->cat($b, 'Makanan & Minuman');

        $c = Livewire::actingAs($b)->test(TransactionForm::class)
            ->call('open')->set('amount', '50000')->set('categoryId', (string) $cat->id)->call('save');

        $n = PartnerNotification::where('user_id', $a->id)->where('type', 'tx_created')->firstOrFail();
        $this->assertSame('Budi mencatat pengeluaran · Rp 50.000 · Makanan & Minuman', $n->message);

        $t = Transaction::firstOrFail();
        $c->call('open', $t->id)->set('amount', '60000')->call('save');
        $c->call('open', $t->id)->call('delete');

        $this->assertSame(3, PartnerNotification::where('user_id', $a->id)->where('type', 'like', 'tx_%')->count());
        $this->assertSame(0, PartnerNotification::where('user_id', $b->id)->where('type', 'like', 'tx_%')->count());
    }

    public function test_solo_user_creates_no_notifications(): void
    {
        $u = $this->user();
        Livewire::actingAs($u)->test(TransactionForm::class)
            ->call('open')->set('amount', '1000')->set('categoryId', (string) $this->cat($u, 'Tagihan')->id)->call('save');
        $this->assertSame(0, PartnerNotification::count());
    }

    public function test_bell_counts_and_marks_read(): void
    {
        [$a, $b] = $this->pair(); // a menerima notifikasi "terhubung"
        $c = Livewire::actingAs($a)->test(\App\Livewire\NotificationBell::class);
        $this->assertSame(1, $c->viewData('unread'));
        $c->call('markAllRead');
        $this->assertSame(0, $c->viewData('unread'));
    }

    public function test_push_subscribe_and_unsubscribe(): void
    {
        $u = $this->user();
        $payload = ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc', 'keys' => ['p256dh' => 'k', 'auth' => 'a']];

        $this->actingAs($u)->postJson('/push/subscribe', $payload)->assertOk();
        $this->actingAs($u)->postJson('/push/subscribe', $payload)->assertOk(); // idempoten
        $this->assertSame(1, $u->pushSubscriptions()->count());

        $this->actingAs($u)->postJson('/push/unsubscribe', ['endpoint' => $payload['endpoint']])->assertOk();
        $this->assertSame(0, $u->pushSubscriptions()->count());
    }

    public function test_partner_page_flow_via_livewire(): void
    {
        $a = $this->user('Rina');
        $b = $this->user('Budi');

        Livewire::actingAs($a)->test(Partner::class)->call('createInvite');
        $code = \App\Models\PartnerInvite::firstOrFail()->code;

        Livewire::actingAs($b)->test(Partner::class)->set('code', strtolower($code))->call('accept')->assertRedirect(route('profile'));
        $this->assertSame($a->fresh()->household_id, $b->fresh()->household_id);

        Livewire::actingAs($b->fresh())->test(Partner::class)->call('disconnect')->assertRedirect(route('profile'));
        $this->assertNotSame($a->fresh()->household_id, $b->fresh()->household_id);
    }

    public function test_reset_password_and_cleanup_commands(): void
    {
        $u = $this->user();
        $this->artisan('user:reset-password', ['email' => $u->email, 'password' => 'barubaru123'])->assertSuccessful();
        $this->assertTrue(\Hash::check('barubaru123', $u->fresh()->password));
        $this->artisan('user:reset-password', ['email' => $u->email, 'password' => 'pendek'])->assertFailed();

        $shared = app(HouseholdService::class)->demoUser();
        $shared->forceFill(['created_at' => now()->subDays(5)])->save();
        // sisa akun demo versi lama (satu akun per kunjungan) harus dibersihkan
        $demo = app(HouseholdService::class)->register('Demo', 'demo-lama@demo.moneygemen', 'password123', true);
        $demo->forceFill(['created_at' => now()->subDays(2)])->save();
        PartnerNotification::create(['user_id' => $u->id, 'type' => 'x', 'message' => 'lama'])
            ->forceFill(['created_at' => now()->subDays(91)])->save();

        $this->artisan('moneygemen:cleanup')->assertSuccessful();
        $this->assertNull(User::find($demo->id));
        $this->assertNotNull(User::find($shared->id)); // demo bersama tidak ikut terhapus
        $this->assertSame(0, PartnerNotification::count());
        $this->assertNotNull(User::find($u->id));
    }
}
