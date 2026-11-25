<div>
    <div class="auth-head">
        <img src="/icons/icon-192.png" alt="" width="64" height="64">
        <h1>Buat akun</h1>
        <p class="muted">Gratis. Kamu langsung dapat 12 kategori bawaan.</p>
    </div>

    <form wire:submit="register" class="card stack">
        <label class="field">
            <span>Nama</span>
            <input type="text" wire:model="name" autocomplete="name" maxlength="50" required>
            @error('name') <small class="err">{{ $message }}</small> @enderror
        </label>

        <label class="field">
            <span>Email</span>
            <input type="email" wire:model="email" autocomplete="email" inputmode="email" required>
            @error('email') <small class="err">{{ $message }}</small> @enderror
        </label>

        <label class="field">
            <span>Kata sandi</span>
            <input type="password" wire:model="password" autocomplete="new-password" minlength="8" required>
            <small class="muted">Minimal 8 karakter.</small>
            @error('password') <small class="err">{{ $message }}</small> @enderror
        </label>

        <button class="btn btn-primary btn-block" type="submit" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="register">Daftar</span>
            <span wire:loading wire:target="register">Memproses…</span>
        </button>
    </form>

    <p class="auth-alt">Sudah punya akun? <a href="{{ route('login') }}">Masuk</a></p>
</div>
