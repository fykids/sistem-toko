@extends('layout')

@section('page_class', 'page-shell--jual page-shell--jual-form')

@section('content')
<section class="jual-form-heading" aria-labelledby="jual-form-title">
    <div>
        <p class="showroom-page-eyebrow">Menu Jual · {{ $isEditing ? 'Perbarui data' : 'Input baru' }}</p>
        <h1 id="jual-form-title">{{ $isEditing ? 'Edit transaksi Jual' : 'Input Penjualan' }}</h1>
        <p>Data yang dicatat di sini tidak mengubah transaksi kendaraan lama.</p>
    </div>
    <a href="{{ route('jual.index') }}" class="btn btn-secondary">Kembali ke Jual</a>
</section>

<form action="{{ $isEditing ? route('jual.update', $saleNote) : route('jual.store') }}" method="POST"
    @class([
        'transaction-form',
        'jual-form',
        'sell-module-form',
        'transaction-form--master' => $canViewSensitiveData,
        'transaction-form--admin' => ! $canViewSensitiveData,
    ])>
    @csrf
    @if ($isEditing)
        @method('PUT')
    @endif

    @if ($canViewSensitiveData)
        @include('jual.components.form-master')
    @else
        @include('jual.components.form-admin')
    @endif
</form>
@endsection
