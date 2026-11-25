<?php

namespace Tests\Feature;

use App\Livewire\Categories;
use App\Livewire\Profile;
use App\Livewire\TransactionForm;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\HouseholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DemoReadOnlyTest extends TestCase
{
    use RefreshDatabase;

    private function demo(): User
    {
        return app(HouseholdService::class)->demoUser();
    }

    public function test_demo_is_one_shared_account_not_one_per_visit(): void
    {
        $this->get('/demo')->assertRedirect('/');
        $this->post('/keluar');
        $this->get('/demo')->assertRedirect('/');
        $this->post('/keluar');
        $this->get('/demo')->assertRedirect('/');

        $this->assertSame(1, User::where('is_demo', true)->count());
        $this->assertSame(1, \App\Models\Household::count());
        $this->assertSame(10, Transaction::count()); // data contoh tidak berlipat ganda
    }

    public function test_demo_data_is_refreshed_when_stale(): void
    {
        $demo = $this->demo();
        Transaction::where('household_id', $demo->household_id)->update(['date' => now()->subDays(30)->toDateString()]);

        $this->demo();

        $this->assertSame(10, Transaction::where('household_id', $demo->household_id)->count());
        $this->assertSame(now()->toDateString(), substr((string) Transaction::where('household_id', $demo->household_id)->max('date'), 0, 10));
    }

    public function test_demo_cannot_add_edit_or_delete_transactions(): void
    {
        $demo = $this->demo();
        $existing = Transaction::where('household_id', $demo->household_id)->first();
        $cat = Category::where('household_id', $demo->household_id)->where('type', 'expense')->first();
        $before = Transaction::count();

        // membuka form saja sudah ditolak
        Livewire::actingAs($demo)->test(TransactionForm::class)
            ->call('open')->assertSet('show', false)->assertDispatched('toast');

        // melewati UI (memaksa state) tetap ditolak di server
        Livewire::actingAs($demo)->test(TransactionForm::class)
            ->set('amount', '1000')->set('categoryId', (string) $cat->id)->call('save')->assertDispatched('toast');
        Livewire::actingAs($demo)->test(TransactionForm::class)
            ->set('editingId', $existing->id)->set('amount', '1')->call('save');
        Livewire::actingAs($demo)->test(TransactionForm::class)
            ->set('editingId', $existing->id)->call('delete');

        $this->assertSame($before, Transaction::count());
        $this->assertNotSame(1, $existing->fresh()->amount);
    }

    public function test_demo_cannot_change_categories(): void
    {
        $demo = $this->demo();
        $cat = Category::where('household_id', $demo->household_id)->where('name', 'Belanja')->firstOrFail();
        $count = Category::count();

        $c = Livewire::actingAs($demo)->test(Categories::class);
        $c->call('create')->assertSet('showForm', false);
        $c->set('name', 'Baru')->set('color', '#112233')->set('icon', '🍜')->call('save');
        $c->call('edit', $cat->id)->assertSet('showForm', false);
        $c->set('editingId', $cat->id)->set('name', 'Diganti')->call('save');
        $c->call('delete', Category::where('household_id', $demo->household_id)->where('name', 'Hiburan')->value('id'));

        $this->assertSame($count - 0, Category::count());
        $this->assertSame('Belanja', $cat->fresh()->name);
        $this->assertNotNull(Category::where('household_id', $demo->household_id)->where('name', 'Hiburan')->first());
    }

    public function test_demo_cannot_change_profile_or_push_or_partner(): void
    {
        $demo = $this->demo();

        Livewire::actingAs($demo)->test(Profile::class)->set('name', 'Hacker')->call('saveName');
        $this->assertSame('Demo', $demo->fresh()->name);

        $this->actingAs($demo)->postJson('/push/subscribe', [
            'endpoint' => 'https://push.example.com/x', 'keys' => ['p256dh' => 'k', 'auth' => 'a'],
        ])->assertForbidden();
        $this->assertSame(0, $demo->pushSubscriptions()->count());

        $this->expectException(\RuntimeException::class);
        app(HouseholdService::class)->createInvite($demo);
    }

    public function test_demo_can_still_browse_everything_and_export(): void
    {
        $this->get('/demo');
        foreach (['/', '/transaksi', '/kategori', '/laporan', '/profil'] as $p) {
            $this->get($p)->assertOk();
        }
        $this->get('/')->assertSee('hanya untuk melihat-lihat');
        $this->get('/ekspor.csv')->assertOk();
    }

    public function test_real_users_are_unaffected(): void
    {
        $u = app(HouseholdService::class)->register('Budi', 'budi@mail.test', 'password123');
        $cat = Category::where('household_id', $u->household_id)->where('name', 'Tagihan')->first();

        Livewire::actingAs($u)->test(TransactionForm::class)
            ->call('open')->assertSet('show', true)
            ->set('amount', '5000')->set('categoryId', (string) $cat->id)->call('save')->assertNotDispatched('toast', error: true);
        $this->assertSame(1, Transaction::where('household_id', $u->household_id)->count());
    }
}
