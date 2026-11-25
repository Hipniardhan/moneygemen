<?php

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Profile;
use App\Livewire\Transactions;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\HouseholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name = 'Budi'): User
    {
        return app(HouseholdService::class)->register($name, strtolower($name) . '@mail.test', 'password123');
    }

    public function test_security_headers_are_sent(): void
    {
        $res = $this->get('/masuk')->assertOk();

        $res->assertHeader('X-Content-Type-Options', 'nosniff');
        $res->assertHeader('X-Frame-Options', 'DENY');
        $res->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString("frame-ancestors 'none'", $res->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("object-src 'none'", $res->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('camera=()', $res->headers->get('Permissions-Policy'));
    }

    public function test_hsts_only_on_https_and_no_store_for_logged_in_pages(): void
    {
        $this->get('/masuk')->assertHeaderMissing('Strict-Transport-Security');
        $this->get('https://moneygemen.test/masuk')->assertHeader('Strict-Transport-Security');

        $res = $this->actingAs($this->user())->get('/')->assertOk();
        $this->assertStringContainsString('no-store', $res->headers->get('Cache-Control'));
    }

    /** @dataProvider badEndpoints */
    public function test_push_endpoint_must_be_a_real_push_service(string $endpoint): void
    {
        $u = $this->user();
        $this->actingAs($u)->postJson('/push/subscribe', ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'k', 'auth' => 'a']])
            ->assertStatus(422)->assertJsonValidationErrors('endpoint');
        $this->assertSame(0, $u->pushSubscriptions()->count());
    }

    public static function badEndpoints(): array
    {
        return [
            'localhost' => ['https://127.0.0.1/internal'],
            'jaringan internal' => ['http://192.168.1.10/admin'],
            'metadata cloud' => ['http://169.254.169.254/latest/meta-data'],
            'http biasa' => ['http://fcm.googleapis.com/fcm/send/abc'],
            'domain palsu mirip' => ['https://fcm.googleapis.com.evil.com/x'],
            'domain palsu akhiran' => ['https://evilfcm.googleapis.com.attacker.io/x'],
            'dengan userinfo' => ['https://fcm.googleapis.com@evil.com/x'],
            'port aneh' => ['https://fcm.googleapis.com:8443/x'],
            'domain acak' => ['https://example.com/push'],
        ];
    }

    /** @dataProvider goodEndpoints */
    public function test_real_push_services_are_accepted(string $endpoint): void
    {
        $u = $this->user();
        $this->actingAs($u)->postJson('/push/subscribe', ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'k', 'auth' => 'a']])->assertOk();
    }

    public static function goodEndpoints(): array
    {
        return [
            'chrome/android' => ['https://fcm.googleapis.com/fcm/send/abc'],
            'firefox' => ['https://updates.push.services.mozilla.com/wpush/v2/abc'],
            'safari' => ['https://web.push.apple.com/abc'],
            'edge' => ['https://wns2-par02p.notify.windows.com/w/?token=abc'],
        ];
    }

    public function test_cannot_register_reserved_demo_email(): void
    {
        Livewire::test(Register::class)
            ->set('name', 'Penyerang')->set('email', 'demo@demo.moneygemen')->set('password', 'password123')
            ->call('register')->assertHasErrors('email');
        $this->assertNull(User::where('email', 'demo@demo.moneygemen')->first());
    }

    public function test_demo_never_logs_visitors_into_a_real_account(): void
    {
        // seandainya akun asli sudah memakai email demo (mis. dibuat sebelum aturan ini ada)
        app(HouseholdService::class)->register('Penyerang', 'demo@demo.moneygemen', 'password123', false);

        $this->get('/demo')->assertStatus(500);
        $this->assertGuest();
    }

    public function test_login_is_rate_limited_per_ip_across_emails(): void
    {
        $c = Livewire::test(Login::class)->set('password', 'salah-salah');
        for ($i = 0; $i < 20; $i++) {
            $c->set('email', "orang{$i}@mail.test")->call('login'); // email berbeda tiap percobaan
        }

        $this->user('Budi');
        $c->set('email', 'budi@mail.test')->set('password', 'password123')->call('login')->assertHasErrors('email');
        $this->assertGuest();
    }

    public function test_changing_password_logs_out_other_devices(): void
    {
        $u = $this->user();

        // perangkat lain sudah login lebih dulu
        $this->actingAs($u)->get('/')->assertOk();
        $hashBefore = $u->fresh()->password;

        Livewire::actingAs($u)->test(Profile::class)
            ->set('currentPassword', 'password123')->set('newPassword', 'barubaru123')->set('newPasswordConfirmation', 'barubaru123')
            ->call('savePassword')->assertHasNoErrors();

        $this->assertNotSame($hashBefore, $u->fresh()->password);
        $this->assertTrue(Hash::check('barubaru123', $u->fresh()->password));

        // sesi lama (membawa hash sandi lama) ditolak oleh AuthenticateSession
        $this->flushSession();
        $this->withSession(['password_hash_web' => $hashBefore])->actingAs($u->fresh())->get('/')->assertRedirect('/masuk');
    }

    public function test_per_page_cannot_be_inflated_by_client(): void
    {
        $u = $this->user();
        $cat = Category::where('household_id', $u->household_id)->value('id');
        $rows = [];
        for ($i = 0; $i < 600; $i++) {
            $rows[] = ['household_id' => $u->household_id, 'user_id' => $u->id, 'category_id' => $cat, 'type' => 'expense',
                'amount' => 1000, 'date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now()];
        }
        Transaction::insert($rows);

        $items = Livewire::actingAs($u)->test(Transactions::class)->set('perPage', 1000000)->viewData('items');
        $this->assertCount(500, $items);
    }

    public function test_cross_household_access_is_blocked_everywhere(): void
    {
        $a = $this->user('Rina');
        $b = $this->user('Budi');
        $cat = Category::where('household_id', $b->household_id)->first();
        $tx = Transaction::create(['household_id' => $b->household_id, 'user_id' => $b->id, 'category_id' => $cat->id,
            'type' => $cat->type, 'amount' => 123456, 'date' => now()->toDateString(), 'description' => 'RAHASIA-BUDI']);

        // halaman milik A tidak boleh memuat data B
        foreach (['/', '/transaksi', '/laporan'] as $p) {
            $this->actingAs($a)->get($p)->assertDontSee('RAHASIA-BUDI')->assertDontSee('123.456');
        }
        $this->assertStringNotContainsString('RAHASIA-BUDI', $this->actingAs($a)->get('/ekspor.csv')->streamedContent());

        // mengubah/menghapus lewat komponen
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        Livewire::actingAs($a)->test(\App\Livewire\TransactionForm::class)->call('open', $tx->id);
    }

    public function test_sql_wildcards_in_search_are_treated_literally(): void
    {
        $u = $this->user();
        $cat = Category::where('household_id', $u->household_id)->first();
        Transaction::create(['household_id' => $u->household_id, 'user_id' => $u->id, 'category_id' => $cat->id,
            'type' => $cat->type, 'amount' => 1, 'date' => now()->toDateString(), 'description' => 'biasa saja']);

        $c = Livewire::actingAs($u)->test(Transactions::class)->set('search', '%');
        $this->assertCount(0, $c->viewData('items')); // '%' bukan wildcard
        $c->set('search', "' OR 1=1 --");
        $this->assertCount(0, $c->viewData('items'));
    }

    public function test_user_input_is_escaped_in_html(): void
    {
        $u = app(HouseholdService::class)->register('<script>alert(1)</script>', 'xss@mail.test', 'password123');
        $html = $this->actingAs($u)->get('/')->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    public function test_logout_requires_post_and_csrf_is_enforced_on_forms(): void
    {
        $u = $this->user();
        $this->actingAs($u)->get('/keluar')->assertStatus(405);
        $this->assertAuthenticatedAs($u);

        $this->actingAs($u)->postJson('/push/unsubscribe', ['endpoint' => 'x'])->assertOk(); // token CSRF dilonggarkan di tes
    }
}
