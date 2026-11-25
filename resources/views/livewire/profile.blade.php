<div class="stack-lg">
    <h1 class="page-title">Profil</h1>

    <section class="card stack">
        <div class="profile-head">
            <span class="avatar">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
            <div class="profile-id">
                <strong>{{ $user->name }}</strong>
                <span class="muted">{{ $user->is_demo ? 'Akun demo' : $user->email }}</span>
            </div>
        </div>

        @if ($user->is_demo)
            <p class="muted">Akun demo bersifat hanya-baca, jadi nama tidak bisa diubah.</p>
        @else
            <form wire:submit="saveName" class="stack">
                <label class="field">
                    <span>Nama</span>
                    <input type="text" wire:model="name" maxlength="50" autocomplete="name">
                    @error('name') <small class="err">{{ $message }}</small> @enderror
                </label>
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveName">Simpan nama</button>
            </form>
        @endif
    </section>

    <h2 class="group-title">Pasangan</h2>
    <livewire:partner />

    <section class="card stack" x-data="pushToggle()" x-show="supported" x-cloak style="display:none">
        <h2 class="section-title">Notifikasi di perangkat ini</h2>
        <p class="muted" x-show="!configured">Notifikasi push belum diaktifkan oleh admin server.</p>
        <p class="muted" x-show="denied">Notifikasi diblokir di pengaturan browser.</p>
        <label class="switch-row" x-show="configured && !denied">
            <span>Notifikasi push</span>
            <input type="checkbox" role="switch" :checked="on" :disabled="busy" @change="toggle($event)">
        </label>
    </section>

    @unless ($user->is_demo)
        <section class="card">
            <h2 class="section-title">Ganti kata sandi</h2>
            <form wire:submit="savePassword" class="stack">
                <label class="field">
                    <span>Kata sandi saat ini</span>
                    <input type="password" wire:model="currentPassword" autocomplete="current-password">
                    @error('currentPassword') <small class="err">{{ $message }}</small> @enderror
                </label>
                <label class="field">
                    <span>Kata sandi baru</span>
                    <input type="password" wire:model="newPassword" autocomplete="new-password" minlength="8">
                    <small class="muted">Minimal 8 karakter.</small>
                    @error('newPassword') <small class="err">{{ $message }}</small> @enderror
                </label>
                <label class="field">
                    <span>Ulangi kata sandi baru</span>
                    <input type="password" wire:model="newPasswordConfirmation" autocomplete="new-password">
                    @error('newPasswordConfirmation') <small class="err">{{ $message }}</small> @enderror
                </label>
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="savePassword">Ganti kata sandi</button>
            </form>
        </section>
    @endunless

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="btn btn-ghost-danger btn-block">Keluar</button>
    </form>
</div>
