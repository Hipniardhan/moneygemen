<div class="stack-lg">
    <div class="row-between">
        <h1 class="page-title">Kategori</h1>
        <button type="button" class="btn btn-sm btn-primary" wire:click="create">+ Baru</button>
    </div>

    <div class="seg">
        <button type="button" wire:click="setTab('expense')" class="{{ $tab === 'expense' ? 'on' : '' }}">Pengeluaran</button>
        <button type="button" wire:click="setTab('income')" class="{{ $tab === 'income' ? 'on' : '' }}">Pemasukan</button>
    </div>

    <div class="list card-flush">
        @forelse ($categories as $c)
            <button type="button" class="tx" wire:key="cat-{{ $c->id }}" wire:click="edit({{ $c->id }})">
                <span class="tx-icon" style="background: {{ $c->color }}22; color: {{ $c->color }}">{{ $c->icon }}</span>
                <span class="tx-main">
                    <span class="tx-title">{{ $c->name }}</span>
                    <span class="tx-meta">{{ $c->transactions_count }} transaksi</span>
                </span>
                <span class="chev" aria-hidden="true">›</span>
            </button>
        @empty
            <p class="empty">Belum ada kategori.</p>
        @endforelse
    </div>

    @if ($showForm)
        <div class="modal-wrap" x-data x-on:keydown.escape.window="$wire.close()">
            <div class="backdrop" wire:click="close"></div>
            <form class="sheet" wire:submit="save" role="dialog" aria-modal="true">
                <div class="sheet-grip"></div>
                <div class="row-between">
                    <h2 class="sheet-title">{{ $editingId ? 'Ubah kategori' : 'Kategori baru' }}</h2>
                    <button type="button" class="icon-btn" wire:click="close" aria-label="Tutup">✕</button>
                </div>

                @unless ($editingId)
                    <div class="seg seg-type">
                        <button type="button" wire:click="$set('type', 'expense')" class="{{ $type === 'expense' ? 'on expense' : '' }}">Pengeluaran</button>
                        <button type="button" wire:click="$set('type', 'income')" class="{{ $type === 'income' ? 'on income' : '' }}">Pemasukan</button>
                    </div>
                @endunless

                <label class="field">
                    <span>Nama</span>
                    <input type="text" wire:model="name" maxlength="60" placeholder="Contoh: Langganan">
                    @error('name') <small class="err">{{ $message }}</small> @enderror
                </label>

                {{-- warna & ikon dikelola di sisi browser (Alpine) dan dikirim saat Simpan --}}
                <div class="stack" x-data="{ c: @entangle('color'), i: @entangle('icon') }">
                    <div class="cat-preview" aria-hidden="true">
                        <span class="tx-icon" :style="`background:${c}22;color:${c}`" x-text="i || '❔'"></span>
                        <span class="muted">Pratinjau</span>
                    </div>

                    <div class="field">
                        <span>Warna</span>
                        <div class="swatches">
                            @foreach ($colors as $col)
                                <button type="button" class="swatch" :class="{ on: c === '{{ $col }}' }" style="background: {{ $col }}" @click="c = '{{ $col }}'" aria-label="Warna {{ $col }}"></button>
                            @endforeach
                        </div>
                        <label class="rgb-pick">
                            <input type="color" x-model="c" aria-label="Pilih warna sendiri (RGB)">
                            <span>Pilih warna sendiri</span>
                            <code x-text="c"></code>
                        </label>
                        @error('color') <small class="err">{{ $message }}</small> @enderror
                    </div>

                    <div class="field">
                        <span>Ikon</span>
                        <input type="text" class="emoji-input" x-model="i" maxlength="20" autocomplete="off" inputmode="text"
                               placeholder="Ketik emoji dari keyboard 😀" aria-label="Ikon emoji">
                        <small class="muted">Buka emoji di keyboard HP-mu (😀), atau pilih cepat di bawah.</small>
                        <div class="icon-grid">
                            @foreach ($icons as $ic)
                                <button type="button" class="icon-pick" :class="{ on: i === '{{ $ic }}' }" @click="i = '{{ $ic }}'">{{ $ic }}</button>
                            @endforeach
                        </div>
                        @error('icon') <small class="err">{{ $message }}</small> @enderror
                    </div>
                </div>

                <div class="sheet-actions">
                    @if ($editingId)
                        <button type="button" class="btn btn-danger" wire:click="delete({{ $editingId }})" wire:confirm="Hapus kategori ini?">Hapus</button>
                    @endif
                    <button type="submit" class="btn btn-primary grow">Simpan</button>
                </div>
            </form>
        </div>
    @endif
</div>
