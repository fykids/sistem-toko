@props(['user'])

<div class="showroom-topbar-account" data-profile-menu>
    <button class="profile-menu-trigger" id="profile-menu-trigger-{{ $user->getKey() }}" type="button"
        aria-expanded="false" aria-haspopup="menu" aria-controls="profile-menu-{{ $user->getKey() }}"
        data-profile-menu-toggle>
        <span class="session-user">
            <strong>{{ $user->name }}</strong>
            <span class="session-role session-role--{{ $user->role }}">{{ ucfirst($user->role) }}</span>
        </span>
        <svg class="profile-menu-chevron" aria-hidden="true" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="m7 10 5 5 5-5" />
        </svg>
    </button>

    <div class="profile-menu-panel" id="profile-menu-{{ $user->getKey() }}" role="menu"
        aria-labelledby="profile-menu-trigger-{{ $user->getKey() }}" data-profile-menu-panel hidden>
        <div class="profile-menu-identity">
            <strong>{{ $user->name }}</strong>
            <span>{{ $user->email }}</span>
        </div>

        <div class="profile-menu-divider" aria-hidden="true"></div>

        <nav aria-label="Pengaturan akun">
            <a class="profile-menu-item" href="{{ route('profile.edit') }}" role="menuitem"
                data-profile-menu-settings>
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="8" r="3.5" />
                    <path d="M5 20a7 7 0 0 1 14 0" />
                    <path d="M19 3v4m-2-2h4" />
                </svg>
                <span>Pengaturan Akun</span>
            </a>
        </nav>

        <div class="profile-menu-divider" aria-hidden="true"></div>

        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button class="profile-menu-item profile-menu-item--danger" type="submit"
                role="menuitem" data-confirm-logout>
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10 17l5-5-5-5M15 12H3" />
                    <path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6" />
                </svg>
                <span>Logout</span>
            </button>
        </form>
    </div>
</div>
