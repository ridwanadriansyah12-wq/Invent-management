@extends('layouts.app')
@section('title', 'Dashboard – Gudang')

@section('content')
<div class="page-header">
  <h1 class="page-title">Dashboard Gudang</h1>
  <p class="page-sub">{{ now()->isoFormat('dddd, D MMMM Y') }}</p>
</div>

{{-- Stats --}}
<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-icon gray">📦</div>
    <div class="stat-info"><div class="stat-value">{{ $totalItems }}</div><div class="stat-label">Total Barang Aktif</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon danger">⛔</div>
    <div class="stat-info"><div class="stat-value">{{ $outOfStock }}</div><div class="stat-label">Stok Habis</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon warning">⚠️</div>
    <div class="stat-info"><div class="stat-value">{{ $lowStock }}</div><div class="stat-label">Perlu Reorder</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon info">🔄</div>
    <div class="stat-info"><div class="stat-value">{{ $totalTransactionsToday }}</div><div class="stat-label">Transaksi Hari Ini</div></div>
  </div>
</div>

{{-- Quick Actions --}}
<div class="d-flex gap-3 mb-5">
  <a href="{{ route('transactions.create') }}" class="btn btn-primary btn-lg">
    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
    Input Transaksi
  </a>
  <a href="{{ route('items.create') }}" class="btn btn-secondary btn-lg">+ Tambah Barang</a>
  <a href="{{ route('items.index') }}" class="btn btn-secondary btn-lg">📦 Lihat Semua Barang</a>
</div>

<div class="d-flex gap-4" style="flex-wrap:wrap;">
  {{-- Critical Stock --}}
  @if($criticalItems->count() || $outItems->count())
  <div class="card flex-1" style="min-width:300px;">
    <div class="card-header">
      <span class="card-title">⚠️ Stok Kritis & Habis</span>
      <span class="badge badge-danger">{{ $criticalItems->count() + $outItems->count() }}</span>
    </div>
    <div class="table-container">
      <table>
        <thead><tr><th>Barang</th><th>Stok</th><th>Safety Stock</th><th>Status</th></tr></thead>
        <tbody>
          @foreach($outItems->take(5) as $item)
          <tr class="row-critical">
            <td>
              <div class="item-code">{{ $item->code }}</div>
              <div class="item-name">{{ $item->name }}</div>
            </td>
            <td><span class="badge badge-danger">0 {{ $item->unit }}</span></td>
            <td>{{ number_format($item->safety_stock, 1) }}</td>
            <td><span class="badge badge-danger">Habis</span></td>
          </tr>
          @endforeach
          @foreach($criticalItems->take(5) as $item)
          <tr class="row-warning">
            <td>
              <div class="item-code">{{ $item->code }}</div>
              <div class="item-name">{{ $item->name }}</div>
            </td>
            <td><span class="badge badge-warning">{{ number_format($item->stock_on_hand, 0) }} {{ $item->unit }}</span></td>
            <td>{{ number_format($item->safety_stock, 1) }}</td>
            <td><span class="badge badge-warning">Kritis</span></td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
  @endif

  {{-- Today Transactions --}}
  <div class="card flex-1" style="min-width:300px;">
    <div class="card-header">
      <span class="card-title">Transaksi Hari Ini</span>
      <a href="{{ route('transactions.index') }}" class="btn btn-sm btn-secondary">Lihat Semua</a>
    </div>
    <div class="table-container">
      <table>
        <thead><tr><th>Barang</th><th>Tipe</th><th>Qty</th></tr></thead>
        <tbody>
          @forelse($todayTransactions as $t)
          <tr>
            <td>
              <div class="item-code">{{ $t->item?->code }}</div>
              <div class="item-name">{{ $t->item?->name }}</div>
            </td>
            <td>
              <span class="badge {{ $t->type === 'in' ? 'badge-success' : ($t->type === 'out' ? 'badge-danger' : 'badge-info') }}">
                {{ $t->type_label }}
              </span>
            </td>
            <td>{{ number_format($t->quantity, 0) }}</td>
          </tr>
          @empty
          <tr><td colspan="3" style="text-align:center;padding:30px;color:var(--text-muted);">Belum ada transaksi hari ini</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
