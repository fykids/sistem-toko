@extends('layout')

@section('page_class', 'page-shell--settings')

@section('content')
    <section class="showroom-content profile-settings-page">
        <header class="showroom-dashboard-heading">
            <p class="showroom-page-eyebrow">Pengaturan sistem</p>
            <h1>Pengaturan Akun Master</h1>
            <p class="showroom-page-description">
                Kelola informasi profil, email, dan kata sandi akses akun Anda.
            </p>
        </header>

        <section class="panel showroom-inventory profile-settings-card" aria-labelledby="profile-settings-title">
            <div class="toolbar">
                <div>
                    <h2 class="toolbar-title" id="profile-settings-title">Informasi akun</h2>
                    <span class="cell-secondary">Perbarui detail profil dan keamanan akun.</span>
                </div>
            </div>

            <form action="{{ route('profile.update') }}" method="POST" class="grid gap-5 p-5 sm:p-6">
                @csrf
                @method('PUT')

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="form-group">
                        <label for="profile-name">Nama Lengkap</label>
                        <input id="profile-name" type="text" name="name" value="{{ old('name', auth()->user()->name) }}"
                            autocomplete="name" maxlength="255" required>
                        @error('name')
                            <span class="users-field-error">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="form-group">
                        <label for="profile-email">Email</label>
                        <input id="profile-email" type="email" name="email" value="{{ old('email', auth()->user()->email) }}"
                            autocomplete="email" maxlength="255" required>
                        @error('email')
                            <span class="users-field-error">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div class="grid gap-4 border-t border-[var(--color-border-subtle)] pt-5 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <h3 class="toolbar-title">Ubah kata sandi</h3>
                        <p class="cell-secondary">Kosongkan bagian ini jika tidak ingin mengganti kata sandi.</p>
                    </div>
                    <div class="form-group">
                        <label for="profile-current-password">Password Saat Ini</label>
                        <input id="profile-current-password" type="password" name="current_password"
                            autocomplete="current-password">
                        @error('current_password')
                            <span class="users-field-error">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="form-group">
                        <label for="profile-password">Password Baru</label>
                        <input id="profile-password" type="password" name="password" autocomplete="new-password"
                            minlength="8">
                        @error('password')
                            <span class="users-field-error">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="form-group">
                        <label for="profile-password-confirmation">Konfirmasi Password</label>
                        <input id="profile-password-confirmation" type="password" name="password_confirmation"
                            autocomplete="new-password">
                    </div>
                </div>

                <div class="flex justify-end border-t border-[var(--color-border-subtle)] pt-5">
                    <button class="showroom-primary-action" type="submit">Simpan Perubahan</button>
                </div>
            </form>
        </section>
    </section>
@endsection
