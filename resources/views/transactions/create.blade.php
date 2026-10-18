@extends('layout')

@section('page_class', 'page-shell--transaction-form')

@section('content')
@php($transaction = $transaction ?? null)
@php($isEditing = $transaction !== null)
<div class="page-heading">
    <a href="{{ route('transactions.index') }}" class="btn btn-secondary">← Kembali ke transaksi</a>
</div>
<form action="{{ $isEditing ? route('transactions.update', $transaction) : route('transactions.store') }}" method="POST"
    class="transaction-form">
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
    @include('transactions.partials.marketing-fields', ['transaction' => $transaction])
    @include('transactions.partials.sales-fields', ['transaction' => $transaction])
    @include('transactions.partials.costs-fields', ['transaction' => $transaction])

    @include('transactions.partials.leasing-options', ['transaction' => $transaction])

    <div class="form-actions"><a href="{{ route('transactions.index') }}" class="btn btn-secondary">Batal</a><button
            type="submit" class="btn btn-accent">{{ $isEditing ? 'Simpan perubahan' : 'Simpan transaksi' }}</button>
    </div>
</form>

@include('transactions.partials.repeatable-item-templates')
@endsection