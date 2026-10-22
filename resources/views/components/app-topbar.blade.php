@props(['breadcrumbLabel', 'breadcrumbUrl', 'pageTitle'])

<header class="showroom-topbar">
    <div class="showroom-topbar-inner">
        <button class="app-menu-toggle" type="button" aria-label="Buka navigasi"
            aria-expanded="false" aria-controls="app-navigation" data-app-menu-toggle>
            <svg class="app-menu-icon app-menu-icon--open" aria-hidden="true" viewBox="0 0 24 24"
                fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                <path d="M4 7h16M4 12h16M4 17h16" />
            </svg>
            <svg class="app-menu-icon app-menu-icon--close" aria-hidden="true" viewBox="0 0 24 24"
                fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                <path d="m6 6 12 12M18 6 6 18" />
            </svg>
        </button>
        <nav class="showroom-topbar-context" aria-label="Breadcrumb">
            <a href="{{ $breadcrumbUrl }}">{{ $breadcrumbLabel }}</a>
            <span aria-hidden="true">/</span>
            <strong @if (request()->routeIs('dashboard', 'transactions.index')) data-current-view-title @endif>
                {{ $pageTitle }}
            </strong>
        </nav>
        <x-user-profile-menu :user="auth()->user()" />
    </div>
</header>
