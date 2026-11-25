<button type="button" class="tx" wire:key="tx-{{ $t->id }}" onclick="Livewire.dispatch('open-transaction', { id: {{ $t->id }} })">
    <span class="tx-icon" style="background: {{ $t->category->color }}22; color: {{ $t->category->color }}">{{ $t->category->icon }}</span>
    <span class="tx-main">
        <span class="tx-title">{{ $t->description ?: $t->category->name }}</span>
        <span class="tx-meta">
            {{ $t->category->name }} · {{ $t->date->translatedFormat('j M Y') }}
            @if ($hasPartner) · dicatat oleh {{ $t->user_id === auth()->id() ? 'kamu' : $t->user->name }} @endif
        </span>
    </span>
    <span class="tx-amount amt {{ $t->type }}">{{ $t->type === 'income' ? '+' : '−' }}Rp {{ number_format($t->amount, 0, ',', '.') }}</span>
</button>
