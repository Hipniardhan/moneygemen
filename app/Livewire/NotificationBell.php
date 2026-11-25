<?php

namespace App\Livewire;

use App\Models\PartnerNotification;
use Livewire\Component;

class NotificationBell extends Component
{
    public bool $open = false;

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    public function markAllRead(): void
    {
        PartnerNotification::where('user_id', auth()->id())->whereNull('read_at')->update(['read_at' => now()]);
    }

    public function render()
    {
        $uid = auth()->id();

        return view('livewire.notification-bell', [
            'unread' => PartnerNotification::where('user_id', $uid)->whereNull('read_at')->count(),
            'items' => $this->open
                ? PartnerNotification::where('user_id', $uid)->latest('id')->limit(30)->get()
                : collect(),
        ]);
    }
}
