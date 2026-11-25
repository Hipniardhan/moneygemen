<div>
    @if ($show)
        <div class="modal-wrap" x-data x-on:keydown.escape.window="$wire.close()">
            <div class="backdrop" wire:click="close"></div>
            <form class="sheet" wire:submit="save" role="dialog" aria-modal="true" aria-label="{{ $editingId ? 'Ubah transaksi' : 'Tambah transaksi' }}">
                <div class="sheet-grip"></div>
                <div class="row-between">
                    <h2 class="sheet-title">{{ $editingId ? 'Ubah transaksi' : 'Catat transaksi' }}</h2>
                    <button type="button" class="icon-btn" wire:click="close" aria-label="Tutup">✕</button>
                </div>

                <div class="seg seg-type">
                    <button type="button" wire:click="setType('expense')" class="{{ $type === 'expense' ? 'on expense' : '' }}">Pengeluaran</button>
                    <button type="button" wire:click="setType('income')" class="{{ $type === 'income' ? 'on income' : '' }}">Pemasukan</button>
                </div>

                <label class="field">
                    <span>Nominal</span>
                    <div class="money-input"
                         x-data="{ raw: @entangle('amount'),
                                   fmt(v) { return String(v ?? '').replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.') } }">
                        <i>Rp</i>
                        <input type="text" inputmode="numeric" autocomplete="off" placeholder="0" maxlength="18"
                               :value="fmt(raw)"
                               @input="raw = $event.target.value.replace(/\D/g, ''); $event.target.value = fmt(raw)">
                    </div>
                    @error('amount') <small class="err">{{ $message }}</small> @enderror
                </label>

                <label class="field">
                    <span>Kategori</span>
                    <select wire:model="categoryId">
                        <option value="">Pilih kategori…</option>
                        @foreach ($categories as $c)
                            <option value="{{ $c->id }}">{{ $c->icon }} {{ $c->name }}</option>
                        @endforeach
                    </select>
                    @error('categoryId') <small class="err">{{ $message }}</small> @enderror
                </label>

                <label class="field">
                    <span>Tanggal</span>
                    <input type="date" wire:model="date">
                    @error('date') <small class="err">{{ $message }}</small> @enderror
                </label>

                <label class="field">
                    <span>Catatan <em class="muted">(opsional)</em></span>
                    <input type="text" wire:model="description" maxlength="255" placeholder="Contoh: makan siang">
                    @error('description') <small class="err">{{ $message }}</small> @enderror
                </label>

                <div class="sheet-actions">
                    @if ($editingId)
                        <button type="button" class="btn btn-danger" wire:click="delete" wire:confirm="Hapus transaksi ini?">Hapus</button>
                    @endif
                    <button type="submit" class="btn btn-primary grow" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save">Simpan</span>
                        <span wire:loading wire:target="save">Menyimpan…</span>
                    </button>
                </div>
            </form>
        </div>
    @endif
</div>
