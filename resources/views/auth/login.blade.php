@extends('layout')

@section('page_class', 'page-shell--auth')

@section('content')
    <section class="auth-card" aria-labelledby="login-title">
        <p class="page-eyebrow">Sistem Toko Mobil</p>
        <h1 id="login-title">Masuk ke akun</h1>
        <p class="auth-description">Gunakan akun Master atau Admin untuk melanjutkan.</p>

        <form action="{{ route('login.store') }}" method="POST" class="auth-form">
            @csrf
            <div class="form-group">
                <label for="email">Email</label>
                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    autocomplete="username"
                    required
                    autofocus
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    id="password"
                    type="password"
                    name="password"
                    autocomplete="current-password"
                    required
                >
            </div>

            <button class="btn btn-accent auth-submit" type="submit">Login</button>
        </form>
    </section>
@endsection
