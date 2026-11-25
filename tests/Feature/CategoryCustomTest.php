<?php

namespace Tests\Feature;

use App\Livewire\Categories;
use App\Livewire\Dashboard;
use App\Models\Category;
use App\Models\User;
use App\Services\HouseholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CategoryCustomTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return app(HouseholdService::class)->register('Budi', 'budi@mail.test', 'password123');
    }

    private function create(User $u, string $name, string $color, string $icon)
    {
        return Livewire::actingAs($u)->test(Categories::class)
            ->call('create')->set('name', $name)->set('color', $color)->set('icon', $icon)->call('save');
    }

    public function test_accepts_custom_rgb_color_and_keyboard_emoji(): void
    {
        $u = $this->user();
        $this->create($u, 'Kopi', '#A1B2C3', '☕')->assertHasNoErrors();

        $c = Category::where('name', 'Kopi')->firstOrFail();
        $this->assertSame('#a1b2c3', $c->color);   // dinormalkan huruf kecil
        $this->assertSame('☕', $c->icon);
    }

    /** @dataProvider validIcons */
    public function test_accepts_complex_emoji(string $icon): void
    {
        $u = $this->user();
        $this->create($u, 'Uji', '#112233', $icon)->assertHasNoErrors();
        $this->assertSame($icon, Category::where('name', 'Uji')->value('icon'));
    }

    public static function validIcons(): array
    {
        return [
            'bendera' => ['🇮🇩'],
            'keluarga (ZWJ)' => ['👨‍👩‍👧'],
            'warna kulit' => ['👍🏽'],
            'dengan variation selector' => ['❤️'],
        ];
    }

    /** @dataProvider invalidIcons */
    public function test_rejects_non_emoji_icons(string $icon): void
    {
        $u = $this->user();
        $this->create($u, 'Uji', '#112233', $icon)->assertHasErrors('icon');
        $this->assertNull(Category::where('name', 'Uji')->first());
    }

    public static function invalidIcons(): array
    {
        return [
            'teks' => ['abc'],
            'satu huruf' => ['a'],
            'html' => ['<b>'],
            'dua emoji' => ['🍜🍜'],
            'angka' => ['1'],
            'kosong' => [''],
            'huruf non-latin' => ['你'],
        ];
    }

    /** @dataProvider invalidColors */
    public function test_rejects_bad_colors(string $color): void
    {
        $u = $this->user();
        $this->create($u, 'Uji', $color, '🍜')->assertHasErrors('color');
    }

    public static function invalidColors(): array
    {
        return [['red'], ['#fff'], ['#12345g'], ['rgb(1,2,3)'], ['#1234567'], ['"><script>']];
    }

    public function test_can_edit_existing_category_to_custom_values(): void
    {
        $u = $this->user();
        $cat = Category::where('household_id', $u->household_id)->where('name', 'Hiburan')->firstOrFail();

        Livewire::actingAs($u)->test(Categories::class)
            ->call('edit', $cat->id)->assertSet('icon', $cat->icon)->assertSet('color', $cat->color)
            ->set('color', '#00FF7F')->set('icon', '🎮')->call('save')->assertHasNoErrors();

        $this->assertSame('#00ff7f', $cat->fresh()->color);
        $this->assertSame('🎮', $cat->fresh()->icon);
    }

    public function test_all_preset_icons_and_default_categories_pass_validation(): void
    {
        foreach (Category::ICONS as $icon) {
            $this->assertTrue(Category::isValidIcon($icon), "preset gagal: $icon");
        }
        foreach (Category::defaults() as [, , , $icon]) {
            $this->assertTrue(Category::isValidIcon($icon), "bawaan gagal: $icon");
        }
    }

    public function test_dashboard_renders_balance_toggle_with_masked_text(): void
    {
        $u = $this->user();
        Livewire::actingAs($u)->test(Dashboard::class)
            ->assertSee('toggleBalance()', false)->assertSee('Rp ••••••');

        $this->actingAs($u)->get('/')->assertSee('mg-hide-balance', false);
    }
}
