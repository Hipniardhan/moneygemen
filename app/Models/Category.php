<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    public const COLORS = [
        '#0f766e', '#16a34a', '#65a30d', '#ca8a04', '#ea580c',
        '#dc2626', '#db2777', '#9333ea', '#4f46e5', '#0284c7',
    ];

    public const ICONS = [
        '💼', '🎁', '📈', '💰', '🍜', '☕', '🛒', '🚗', '⛽', '🧾',
        '💊', '🎬', '📚', '🏠', '📱', '👕', '✈️', '🐶', '🎮', '💡',
        '👶', '🏋️', '🎓', '🏷️',
    ];

    protected $fillable = ['household_id', 'name', 'type', 'color', 'icon'];

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** Tepat satu emoji (termasuk bendera, warna kulit, dan gabungan ZWJ). Tanpa ext-intl. */
    public static function isValidIcon(string $icon): bool
    {
        $re = '/^(?:[\x{1F1E6}-\x{1F1FF}]{2}|\p{Extended_Pictographic}[\x{FE0F}\x{1F3FB}-\x{1F3FF}]?(?:\x{200D}\p{Extended_Pictographic}[\x{FE0F}\x{1F3FB}-\x{1F3FF}]?)*)$/u';
        $ok = @preg_match($re, $icon);

        if ($ok === false) { // PCRE lama di server tanpa properti Unicode: longgarkan
            return mb_strlen($icon) <= 16 && preg_match('/^[^\x00-\x7F]+$/u', $icon) === 1;
        }

        return $ok === 1;
    }

    /** Kategori bawaan untuk household baru. */
    public static function defaults(): array
    {
        return [
            ['Gaji & Upah', 'income', '#16a34a', '💼'],
            ['Bonus & THR', 'income', '#65a30d', '🎁'],
            ['Investasi', 'income', '#0f766e', '📈'],
            ['Pemasukan Lainnya', 'income', '#0284c7', '💰'],
            ['Makanan & Minuman', 'expense', '#ea580c', '🍜'],
            ['Transportasi & Bensin', 'expense', '#4f46e5', '⛽'],
            ['Tagihan', 'expense', '#ca8a04', '🧾'],
            ['Kesehatan', 'expense', '#dc2626', '💊'],
            ['Belanja', 'expense', '#db2777', '🛒'],
            ['Hiburan', 'expense', '#9333ea', '🎬'],
            ['Pendidikan', 'expense', '#0284c7', '📚'],
            ['Pengeluaran Lainnya', 'expense', '#64748b', '🏷️'],
        ];
    }
}
