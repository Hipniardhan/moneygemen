<div class="bell" wire:poll.60s>
    <button type="button" class="icon-btn" wire:click="toggle" aria-label="Notifikasi">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/></svg>
        @if ($unread > 0)
            <span class="badge">{{ $unread > 9 ? '9+' : $unread }}</span>
        @endif
    </button>

    @if ($open)
        <div class="backdrop clear" wire:click="toggle"></div>
        <div class="bell-panel" role="dialog" aria-label="Notifikasi">
            <div class="row-between bell-head">
                <strong>Notifikasi</strong>
                @if ($unread > 0)
                    <button type="button" class="link-sm" wire:click="markAllRead">Tandai semua sudah dibaca</button>
                @endif
            </div>
            <div class="bell-list">
                @forelse ($items as $n)
                    <div class="notif {{ $n->read_at ? '' : 'unread' }}" wire:key="n-{{ $n->id }}">
                        <p>{{ $n->message }}</p>
                        <small class="muted">{{ $n->created_at->diffForHumans() }}</small>
                    </div>
                @empty
                    <p class="empty">Belum ada notifikasi.</p>
                @endforelse
            </div>
        </div>
    @endif
</div>
