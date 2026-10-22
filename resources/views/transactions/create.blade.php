@extends('layout')

@section('page_class', request()->routeIs('transactions.create')
    ? 'page-shell--transaction-form page-shell--transaction-form-create'
    : 'page-shell--transaction-form')

@section('content')
@php($transaction = $transaction ?? null)
@php($isEditing = $transaction !== null)
<form action="{{ $isEditing ? route('transactions.update', $transaction) : route('transactions.store') }}" method="POST"
    enctype="multipart/form-data" @class([
        'transaction-form',
        'transaction-form--master' => auth()->user()->can('viewSensitiveData', \App\Models\CarTransaction::class),
    ])>
    @csrf
    @if ($isEditing)
        @method('PUT')
    @endif
    @if ($errors->any())
        <div class="error-panel" role="alert">
            <strong>Periksa kembali data berikut:</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    @include('transactions.partials.vehicle-fields', ['transaction' => $transaction])
    @include('transactions.partials.costs-fields', ['transaction' => $transaction])
    @include('transactions.partials.marketing-fields', ['transaction' => $transaction])
    @include('transactions.partials.sales-fields', ['transaction' => $transaction])

    <div class="leasing-photo-layout">
        @include('transactions.partials.leasing-options', ['transaction' => $transaction])
        @include('transactions.partials.vehicle-photo', ['transaction' => $transaction])
    </div>

    <div class="form-actions"><a href="{{ route('transactions.index') }}" class="btn btn-secondary">Batal</a><button
            type="submit" class="btn btn-accent">{{ $isEditing ? 'Simpan perubahan' : 'Simpan transaksi' }}</button>
    </div>
</form>

@include('transactions.partials.repeatable-item-templates')
@endsection