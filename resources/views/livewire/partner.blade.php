<div class="stack-lg">
    @error('invite') <p class="alert">{{ $message }}</p> @enderror

    @if ($partner)
        <section class="card partner-card">
            <div class="avatars">
                <span class="avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                <span class="avatar b">{{ mb_strtoupper(mb_substr($partner->name, 0, 1)) }}</span>
            </div>
            <h2>Terhubung dengan {{ $partner->name }}</h2>
            <p class="muted">Kalian melihat dan mengisi catatan keuangan yang sama. Setiap perubahan dikabarkan ke pasanganmu.</p>
        </section>

        <section class="card stack">
            <h2 class="section-title">Putuskan hubungan</h2>
            <p class="muted">Data dipisah secara adil: kamu membawa transaksi yang kamu catat sendiri, {{ $partner->name }} membawa miliknya.</p>
            @if ($confirmDisconnect)
                <div class="row gap">
                    <button class="btn btn-danger grow" wire:click="disconnect" wire:loading.attr="disabled">Ya, putuskan</button>
                    <button class="btn btn-ghost" wire:click="$set('confirmDisconnect', false)">Batal</button>
                </div>
            @else
                <button class="btn btn-ghost-danger" wire:click="$set('confirmDisconnect', true)">Putuskan hubungan…</button>
            @endif
        </section>
    @elseif (auth()->user()->is_demo)
        <p class="card muted">Fitur pasangan tidak tersedia di akun demo. Daftar akun dulu untuk mencobanya.</p>
    @else
        <section class="card stack">
            <h2 class="section-title">Undang pasangan</h2>
            <p class="muted">Buat kode 8 karakter, lalu kirim ke pasanganmu. Kode berlaku 24 jam dan hanya bisa dipakai sekali.</p>

            @if ($invite)
                <div class="code-box" x-data="{ copied: false }">
                    <span class="code">{{ $invite->code }}</span>
                    <button type="button" class="btn btn-sm btn-ghost"
                            @click="navigator.clipboard?.writeText('{{ $invite->code }}'); copied = true; setTimeout(() => copied = false, 1800)"
                            x-text="copied ? 'Tersalin ✓' : 'Salin'"></button>
                </div>
                <small class="muted">Berlaku sampai {{ $invite->expires_at->translatedFormat('j M Y, H:i') }}</small>
                <button class="btn btn-ghost" wire:click="createInvite">Buat kode baru</button>
            @else
                <button class="btn btn-primary" wire:click="createInvite" wire:loading.attr="disabled">Buat kode undangan</button>
            @endif
        </section>

        <section class="card stack">
            <h2 class="section-title">Punya kode dari pasangan?</h2>
            <p class="muted">Catatan kalian berdua akan digabung. Kategori dengan nama sama otomatis disatukan.</p>
            <form wire:submit="accept" class="stack">
                <label class="field">
                    <input type="text" class="code-input" wire:model="code" maxlength="8" autocomplete="off" autocapitalize="characters" placeholder="ABCD2345">
                    @error('code') <small class="err">{{ $message }}</small> @enderror
                </label>
                <button class="btn btn-primary" type="submit" wire:loading.attr="disabled" wire:target="accept">Gabungkan</button>
            </form>
        </section>
    @endif
</div>
