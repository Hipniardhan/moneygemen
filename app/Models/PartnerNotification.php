<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerNotification extends Model
{
    protected $fillable = ['user_id', 'actor_id', 'type', 'message', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }
}
