<div>
    <div class="auth-head">
        <img src="/icons/logo-full.png?v={{ $iconV }}" alt="" class="auth-logo" width="280" height="266">
        <h1 class="sr-only">MoneyGemen</h1>
        <p class="muted">Catat uang harian, sendiri atau berdua.</p>
    </div>

    <form wire:submit="login" class="card stack">
        <label class="field">
            <span>Email</span>
            <input type="email" wire:model="email" autocomplete="email" inputmode="email" placeholder="nama@email.com" required>
            @error('email') <small class="err">{{ $message }}</small> @enderror
        </label>

        <label class="field">
            <span>Kata sandi</span>
            <input type="password" wire:model="password" autocomplete="current-password" required>
            @error('password') <small class="err">{{ $message }}</small> @enderror
        </label>

        <label class="check"><input type="checkbox" wire:model="remember"> Ingat saya di perangkat ini</label>

        <button class="btn btn-primary btn-block" type="submit" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="login">Masuk</span>
            <span wire:loading wire:target="login">Memproses…</span>
        </button>
    </form>

    <p class="auth-alt">Belum punya akun? <a href="{{ route('register') }}">Daftar</a></p>
    <a href="{{ route('demo') }}" class="btn btn-ghost btn-block">Coba akun demo</a>
</div>
