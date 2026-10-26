@extends('layout')

@section('page_class', 'page-shell--dashboard')

@section('content')
    <div class="showroom-content">
        @include('transactions.partials.dashboard-summary', [
            'stockCount' => $stockCount,
            'soldCount' => $soldCount,
            'search' => $search,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'reportTransactionCount' => $reportTransactionCount,
            'reportStockCount' => $reportStockCount,
            'reportRecordedCapital' => $reportRecordedCapital,
            'reportSoldCount' => $reportSoldCount,
            'reportSoldRevenue' => $reportSoldRevenue,
            'reportSoldCapital' => $reportSoldCapital,
            'reportSoldNetProfit' => $reportSoldNetProfit,
        ])

        @include('transactions.partials.dashboard-records', [
            'transactions' => $transactions,
            'view' => $view,
            'search' => $search,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'canViewSensitiveData' => $canViewSensitiveData,
            'tableColumnCount' => $tableColumnCount,
        ])
    </div>

    @include('transactions.partials.database-backup-modal')
@endsection
