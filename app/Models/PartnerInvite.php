<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerInvite extends Model
{
    protected $fillable = ['household_id', 'created_by', 'code', 'expires_at', 'used_at', 'used_by'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime'];
    }

    public function isActive(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }
}
