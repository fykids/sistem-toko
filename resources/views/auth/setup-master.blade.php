@extends('layout')

@section('page_class', 'page-shell--auth')

@section('content')
    <section class="auth-card" aria-labelledby="setup-master-title">
        <p class="page-eyebrow">Sistem Toko Mobil</p>
        <h1 id="setup-master-title">Buat akun Master</h1>
        <p class="auth-description">Buat akun Master untuk memulai aplikasi.</p>

        <form action="{{ route('setup-master.store') }}" method="POST" class="auth-form">
            @csrf
            <div class="form-group">
                <label for="name">Nama Lengkap</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}"
                    autocomplete="name" maxlength="255" required autofocus>
                @error('name')
                    <span class="form-help" role="alert">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="username">Username</label>
                <input id="username" type="text" name="username" value="{{ old('username') }}"
                    autocomplete="username" maxlength="255" required>
                @error('username')
                    <span class="form-help" role="alert">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="password">Password Baru</label>
                <input id="password" type="password" name="password" autocomplete="new-password"
                    minlength="12" required>
                @error('password')
                    <span class="form-help" role="alert">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="password_confirmation">Konfirmasi Password Baru</label>
                <input id="password_confirmation" type="password" name="password_confirmation"
                    autocomplete="new-password" required>
            </div>

            <button class="btn btn-accent auth-submit" type="submit">Buat Akun Master &amp; Mulai Aplikasi</button>
        </form>
    </section>
@endsection