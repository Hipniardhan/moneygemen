<div class="stack-lg">
    <h1 class="page-title">Transaksi</h1>

    <div class="filters">
        <input type="search" class="search" wire:model.live.debounce.400ms="search" placeholder="Cari catatan atau kategori…" aria-label="Cari">

        <div class="seg">
            <button type="button" wire:click="$set('type', 'all')" class="{{ $type === 'all' ? 'on' : '' }}">Semua</button>
            <button type="button" wire:click="$set('type', 'income')" class="{{ $type === 'income' ? 'on' : '' }}">Pemasukan</button>
            <button type="button" wire:click="$set('type', 'expense')" class="{{ $type === 'expense' ? 'on' : '' }}">Pengeluaran</button>
        </div>

        <div class="filter-row">
            <select wire:model.live="categoryId" aria-label="Kategori">
                <option value="">Semua kategori</option>
                @foreach ($categories as $c)
                    <option value="{{ $c->id }}">{{ $c->icon }} {{ $c->name }}</option>
                @endforeach
            </select>
            <select wire:model.live="sort" aria-label="Urutan">
                <option value="newest">Terbaru</option>
                <option value="oldest">Terlama</option>
                <option value="largest">Terbesar</option>
                <option value="smallest">Terkecil</option>
            </select>
        </div>
    </div>

    <div class="list card-flush" wire:loading.class="dim" wire:target="search,type,categoryId,sort">
        @forelse ($items as $t)
            @include('partials.transaction-row', ['t' => $t, 'hasPartner' => $hasPartner])
        @empty
            <p class="empty">Tidak ada transaksi yang cocok.</p>
        @endforelse
    </div>

    @if ($hasMore)
        <button type="button" class="btn btn-ghost btn-block" wire:click="more" wire:loading.attr="disabled" wire:target="more">
            <span wire:loading.remove wire:target="more">Muat lagi</span>
            <span wire:loading wire:target="more">Memuat…</span>
        </button>
    @endif
</div>
