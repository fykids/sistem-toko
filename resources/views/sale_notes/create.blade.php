@extends('layout')

@section('page_class', 'page-shell--jual-form')

@section('content')
@php($isEditing = $saleNote !== null)
@php($saleNote = $saleNote ?? new \App\Models\SaleNote)
@php($formIndexRoute = 'nota-penjualan.index')
<section class="jual-form-heading" aria-labelledby="sale-note-form-title">
    <div>
        <p class="showroom-page-eyebrow">Nota penjualan</p>
        <h1 id="sale-note-form-title">{{ $isEditing ? 'Edit Nota Penjualan' : 'Buat Nota Penjualan' }}</h1>
        <p>Spesifikasi dan rincian kendaraan ditampilkan dari data stok utama.</p>
    </div>
    <a href="{{ route('nota-penjualan.index') }}" class="btn btn-secondary">Kembali ke daftar</a>
</section>

<form action="{{ $isEditing ? route('nota-penjualan.update', $saleNote) : route('nota-penjualan.store') }}"
    method="POST" @class([
        'transaction-form',
        'jual-form',
        'sell-module-form',
        'transaction-form--sale-note',
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
