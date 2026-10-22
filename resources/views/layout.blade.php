<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Toko Mobil</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="toast-stack" data-toast-stack>
        @if (session('success'))
            <div class="toast toast--success" data-toast role="status">
                <span>{{ session('success') }}</span>
                <button class="toast-close" type="button" data-toast-close aria-label="Tutup pemberitahuan">&times;</button>
            </div>
        @endif
        @if (session('error'))
            <div class="toast toast--error" data-toast role="alert">
                <span>{{ session('error') }}</span>
                <button class="toast-close" type="button" data-toast-close aria-label="Tutup pemberitahuan">&times;</button>
            </div>
        @endif
        @if (session('info'))
            <div class="toast toast--info" data-toast role="status">
                <span>{{ session('info') }}</span>
                <button class="toast-close" type="button" data-toast-close aria-label="Tutup pemberitahuan">&times;</button>
            </div>
        @endif
    </div>
    <main @class([
        'page-shell',
        trim($__env->yieldContent('page_class')),
        'page-shell--app' => auth()->check()
            && ! request()->routeIs('transactions.create', 'transactions.edit'),
    ]) @if (request()->routeIs('dashboard', 'transactions.index')) data-showroom-layout data-showroom-view="dashboard" @endif>
        @auth
            @php
                $isTransactionPage = request()->routeIs(
                    'transactions.create',
                    'transactions.edit',
                    'transactions.show',
                );
                $isUserPage = request()->routeIs('users.*');
                $isProfilePage = request()->routeIs('profile.*');
                $pageTitle = $isTransactionPage
                    ? (request()->routeIs('transactions.edit')
                        ? 'Edit Transaksi'
                        : (request()->routeIs('transactions.show') ? 'Detail Transaksi' : 'Transaksi Baru'))
                    : ($isUserPage
                        ? 'Manajemen Akun'
                        : ($isProfilePage
                            ? 'Pengaturan Akun'
                            : (request()->routeIs('dashboard', 'transactions.index') ? 'Dashboard' : 'Akun')));
                $breadcrumbLabel = $isTransactionPage
                    ? 'Transaksi'
                    : ($isProfilePage ? 'Pengaturan' : 'Showroom');
                $breadcrumbUrl = $isTransactionPage
                    ? route('transactions.index')
                    : route('dashboard');
            @endphp
            <x-app-topbar
                :breadcrumb-label="$breadcrumbLabel"
                :breadcrumb-url="$breadcrumbUrl"
                :page-title="$pageTitle"
            />
            <x-app-sidebar />
        @endauth
        @if ($errors->any())
            <div class="error-panel" role="alert">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @if (request()->routeIs('dashboard', 'transactions.index', 'users.index', 'profile.edit', 'transactions.create', 'transactions.edit'))
            @yield('content')
        @else
            <div class="showroom-content">
                @yield('content')
            </div>
        @endif
    </main>
</body>
</html>
