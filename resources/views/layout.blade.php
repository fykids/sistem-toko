<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Toko Mobil</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <main class="page-shell @yield('page_class')">
        @if (session('success'))
            <div class="flash-message" role="status">{{ session('success') }}</div>
        @endif
        @if (session('info'))
            <div class="flash-message flash-message--info" role="status">{{ session('info') }}</div>
        @endif
        @auth
            @unless (request()->routeIs('dashboard', 'transactions.index'))
            <header class="session-toolbar">
                <a class="btn btn-secondary btn-small" href="{{ route('dashboard') }}">Dashboard</a>
                @can('viewAny', \App\Models\User::class)
                    <a class="btn btn-secondary btn-small" href="{{ route('users.index') }}">Manajemen Akun</a>
                @endcan
                <div class="session-user">
                    <strong>{{ auth()->user()->name }}</strong>
                    <span class="session-role session-role--{{ auth()->user()->role }}">{{ ucfirst(auth()->user()->role) }}</span>
                </div>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button class="btn btn-secondary btn-small" type="submit">Logout</button>
                </form>
            </header>
            @endunless
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
        @yield('content')
    </main>
</body>
</html>