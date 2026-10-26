<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Katalog Stok') · Sistem Toko Mobil</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[var(--color-surface-canvas)] text-[var(--color-text-primary)]">
    <header class="border-b border-[var(--color-border)] bg-[var(--color-surface)]">
        <div class="mx-auto flex w-full max-w-7xl flex-wrap items-center justify-between gap-4 px-4 py-4 sm:px-6">
            <a href="{{ route('marketing.stok.index') }}" class="flex items-center gap-3 text-inherit no-underline">
                <span class="grid size-10 place-items-center rounded-xl bg-[var(--color-primary)] font-bold text-white">ST</span>
                <span>
                    <strong class="block text-base">Sistem Toko Mobil</strong>
                    <span class="text-sm text-[var(--color-text-secondary)]">Katalog stok kendaraan</span>
                </span>
            </a>
            <form action="{{ route('marketing.stok.index') }}" method="GET"
                class="order-3 flex w-full gap-2 sm:order-none sm:w-auto sm:max-w-xl sm:flex-1">
                <label class="sr-only" for="marketing-search">Cari plat nomor, merek, atau model</label>
                <input id="marketing-search"
                    class="min-h-10 min-w-0 flex-1 rounded-lg border border-[var(--color-control-border)] bg-[var(--color-control-surface)] px-3 text-sm"
                    type="search" name="q" value="{{ request()->query('q') }}"
                    placeholder="Cari plat nomor, merek, atau model" autocomplete="off">
                <button class="btn btn-accent" type="submit">Cari</button>
            </form>
            <div class="flex items-center gap-3">
                <span class="text-xs text-[var(--color-text-secondary)] sm:text-sm">
                    {{ auth()->user()->name }}
                    <span class="ml-1 rounded-full bg-[var(--color-primary-soft)] px-2 py-1 text-xs font-semibold text-[var(--color-primary-deep)]">
                        Marketing
                    </span>
                </span>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button class="btn btn-secondary" type="submit">Logout</button>
                </form>
            </div>
        </div>
    </header>

    @if (session('success') || session('info'))
        <div class="mx-auto mt-4 w-full max-w-7xl px-4 sm:px-6" role="status">
            <p class="rounded-lg border border-[var(--color-border-primary)] bg-[var(--color-surface-success)] px-4 py-3 text-sm">
                {{ session('success') ?? session('info') }}
            </p>
        </div>
    @endif

    @if ($errors->any())
        <div class="mx-auto mt-4 w-full max-w-7xl px-4 sm:px-6" role="alert">
            <ul class="rounded-lg border border-[var(--color-danger-border)] bg-[var(--color-surface-danger)] px-4 py-3 text-sm">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <main class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 sm:py-8">
        @yield('content')
    </main>
</body>
</html>
