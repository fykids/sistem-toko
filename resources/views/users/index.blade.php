@extends('layout')

@section('page_class', 'page-shell--users')

@section('content')
    <section class="showroom-content users-page" data-user-management>
        <header class="showroom-dashboard-heading">
            <p class="showroom-page-eyebrow">Pengaturan sistem</p>
            <h1>Manajemen Akun</h1>
            <p class="showroom-page-description">Kelola akses Master dan Admin showroom dalam satu tempat.</p>
        </header>

        <div class="panel showroom-inventory users-table-card">
            <div class="toolbar">
                <div>
                    <h2 class="toolbar-title">Daftar Pengguna</h2>
                    <span class="cell-secondary">Semua akun terdaftar</span>
                </div>
                <div class="toolbar-actions">
                    <form class="search-wrap" action="{{ route('users.index') }}" method="GET">
                        <span class="search-symbol" aria-hidden="true">⌕</span>
                        <span class="sr-only">Cari nama, username, atau role pengguna</span>
                        <input id="users-search" type="search" name="q" value="{{ $search }}"
                            placeholder="Cari nama, username, role..." autocomplete="off" data-user-search>
                        <button class="sr-only" type="submit">Cari pengguna</button>
                    </form>
                    <button class="showroom-primary-action" type="button" data-open-create-user>
                        <span aria-hidden="true">+</span>
                        Tambah akun
                    </button>
                </div>
            </div>
            <div class="table-scroll">
                <table class="users-table data-table">
                    <thead>
                        <tr>
                            <th scope="col">Nama</th>
                            <th scope="col">Username</th>
                            <th scope="col">Peran (Role)</th>
                            <th scope="col">Tanggal dibuat</th>
                            <th scope="col">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            <tr data-user-row>
                                <td data-label="Nama">
                                    <strong>{{ $user->name }}</strong>
                                    @if (auth()->user()->is($user))
                                        <span class="users-current-account">Anda</span>
                                    @endif
                                </td>
                                <td data-label="Username">{{ $user->username }}</td>
                                <td data-label="Role">
                                    <span class="session-role session-role--{{ $user->role }}">
                                        {{ ucfirst($user->role) }}
                                    </span>
                                </td>
                                <td data-label="Tanggal dibuat">{{ $user->created_at?->format('d M Y') }}</td>
                                <td data-label="Aksi">
                                    <div class="users-row-actions">
                                        <button
                                            class="btn btn-secondary btn-small"
                                            type="button"
                                            data-open-edit-user
                                            data-user-id="{{ $user->getKey() }}"
                                            data-update-url="{{ route('users.update', $user) }}"
                                            data-name="{{ $user->name }}"
                                            data-username="{{ $user->username }}"
                                            data-role="{{ $user->role }}"
                                        >Edit</button>
                                        <form action="{{ route('users.destroy', $user) }}" method="POST"
                                            data-confirm-user-delete>
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                class="btn btn-danger btn-small"
                                                type="submit"
                                                @disabled(auth()->user()->is($user) || ($user->role === 'master' && $masterCount <= 1))
                                                @if (auth()->user()->is($user))
                                                    title="Akun yang sedang digunakan tidak dapat dihapus"
                                                @elseif ($user->role === 'master' && $masterCount <= 1)
                                                    title="Sistem harus memiliki minimal satu akun Master"
                                                @endif
                                            >Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="users-empty-state">Belum ada akun pengguna.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <footer class="table-foot" data-user-count aria-live="polite">
                Menampilkan {{ $users->count() }} pengguna
            </footer>
            @if ($users->hasPages())
                <div class="users-pagination">{{ $users->withQueryString()->links() }}</div>
            @endif
        </div>

        <dialog class="users-dialog" id="create-user-dialog"
            @if (old('_form') === 'create') data-open-on-load @endif>
            <form action="{{ route('users.store') }}" method="POST" class="users-dialog-form">
                @csrf
                <input type="hidden" name="_form" value="create">
                <header class="users-dialog-heading">
                    <div>
                        <p class="page-eyebrow">Akun baru</p>
                        <h2>Tambah pengguna</h2>
                    </div>
                    <button class="users-dialog-close" type="button" aria-label="Tutup" data-close-dialog>&times;</button>
                </header>
                <div class="users-dialog-fields">
                    <div class="form-group">
                        <label for="create-user-name">Nama</label>
                        <input id="create-user-name" type="text" name="name" value="{{ old('_form') === 'create' ? old('name') : '' }}" required maxlength="255" autocomplete="name">
                        @error('name')<span class="users-field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label for="create-user-username">Username</label>
                        <input id="create-user-username" type="text" name="username" value="{{ old('_form') === 'create' ? old('username') : '' }}" required maxlength="255" autocomplete="username">
                        @error('username')<span class="users-field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label for="create-user-password">Password</label>
                        <input id="create-user-password" type="password" name="password" required minlength="8" autocomplete="new-password">
                        @error('password')<span class="users-field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label for="create-user-role">Role</label>
                        <select id="create-user-role" name="role" required>
                            <option value="admin" @selected(old('_form') === 'create' && old('role') === 'admin')>Admin</option>
                            <option value="master" @selected(old('_form') === 'create' && old('role') === 'master')>Master</option>
                            <option value="marketing" @selected(old('_form') === 'create' && old('role') === 'marketing')>Marketing</option>
                        </select>
                        @error('role')<span class="users-field-error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <footer class="users-dialog-actions">
                    <button class="btn btn-secondary" type="button" data-close-dialog>Batal</button>
                    <button class="btn btn-accent" type="submit">Buat akun</button>
                </footer>
            </form>
        </dialog>

        <dialog class="users-dialog" id="edit-user-dialog"
            data-update-url-template="{{ route('users.update', ['user' => '__USER__']) }}"
            @if (old('_form') === 'edit') data-open-on-load data-old-user-id="{{ old('_user_id') }}" @endif>
            <form action="#" method="POST" class="users-dialog-form" id="edit-user-form">
                @csrf
                @method('PUT')
                <input type="hidden" name="_form" value="edit">
                <input type="hidden" name="_user_id" value="{{ old('_user_id') }}">
                <header class="users-dialog-heading">
                    <div>
                        <p class="page-eyebrow">Perbarui akses</p>
                        <h2>Edit pengguna</h2>
                    </div>
                    <button class="users-dialog-close" type="button" aria-label="Tutup" data-close-dialog>&times;</button>
                </header>
                <div class="users-dialog-fields">
                    <div class="form-group">
                        <label for="edit-user-name">Nama</label>
                        <input id="edit-user-name" type="text" name="name" value="{{ old('_form') === 'edit' ? old('name') : '' }}" required maxlength="255" autocomplete="name">
                        @error('name')<span class="users-field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label for="edit-user-username">Username</label>
                        <input id="edit-user-username" type="text" name="username" value="{{ old('_form') === 'edit' ? old('username') : '' }}" required maxlength="255" autocomplete="username">
                        @error('username')<span class="users-field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label for="edit-user-password">Password baru <span>(opsional)</span></label>
                        <input id="edit-user-password" type="password" name="password" minlength="8" autocomplete="new-password">
                        @error('password')<span class="users-field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label for="edit-user-role">Role</label>
                        <select id="edit-user-role" name="role" required>
                            <option value="admin" @selected(old('_form') === 'edit' && old('role') === 'admin')>Admin</option>
                            <option value="master" @selected(old('_form') === 'edit' && old('role') === 'master')>Master</option>
                            <option value="marketing" @selected(old('_form') === 'edit' && old('role') === 'marketing')>Marketing</option>
                        </select>
                        @error('role')<span class="users-field-error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <footer class="users-dialog-actions">
                    <button class="btn btn-secondary" type="button" data-close-dialog>Batal</button>
                    <button class="btn btn-accent" type="submit">Simpan perubahan</button>
                </footer>
            </form>
        </dialog>
    </section>
@endsection
