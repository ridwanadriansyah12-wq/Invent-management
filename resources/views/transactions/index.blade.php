@extends('layouts.app')
@section('title', 'Riwayat Transaksi')

@section('content')
<div class="page-header d-flex align-center justify-between">
  <div>
    <h1 class="page-title">Riwayat Transaksi</h1>
    <p class="page-sub">Semua pergerakan stok barang</p>
  </div>
  @if(auth()->user()->isGudang())
  <a href="{{ route('transactions.create') }}" class="btn btn-primary">+ Input Transaksi</a>
  @endif
</div>

<form method="GET" class="filter-bar mb-4">
  <div class="input-group flex-1" style="max-width:280px;">
    <svg class="input-group-icon" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
    <input type="text" name="search" class="form-control" placeholder="Cari barang..." value="{{ request('search') }}">
  </div>
  <select name="type" class="form-control" style="max-width:160px;">
    <option value="">Semua Tipe</option>
    <option value="in"         {{ request('type') === 'in'         ? 'selected' : '' }}>Stok Masuk</option>
    <option value="out"        {{ request('type') === 'out'        ? 'selected' : '' }}>Stok Keluar</option>
    <option value="adjustment" {{ request('type') === 'adjustment' ? 'selected' : '' }}>Penyesuaian</option>
  </select>
  <input type="date" name="date" class="form-control" style="max-width:160px;" value="{{ request('date') }}">
  <button type="submit" class="btn btn-secondary">Filter</button>
  @if(request()->hasAny(['search','type','date']))
    <a href="{{ route('transactions.index') }}" class="btn btn-secondary">Reset</a>
  @endif
</form>

<div class="card">
  <div class="table-container">
    <table>
      <thead>
        <tr><th>Tanggal</th><th>Kode</th><th>Nama Barang</th><th>Tipe</th><th>Qty</th><th>Sebelum</th><th>Sesudah</th><th>Ref No</th><th>Catatan</th><th>User</th></tr>
      </thead>
      <tbody>
        @forelse($transactions as $t)
        <tr>
          <td class="no-wrap">{{ $t->transaction_date->format('d/m/Y') }}</td>
          <td><span class="item-code">{{ $t->item?->code ?? '-' }}</span></td>
          <td>
            <a href="{{ route('items.show', $t->item_id) }}" style="color:var(--text-primary);font-weight:500;">
              {{ $t->item?->name ?? '-' }}
            </a>
          </td>
          <td>
            <span class="badge {{ $t->type === 'in' ? 'badge-success' : ($t->type === 'out' ? 'badge-danger' : 'badge-info') }}">
              {{ $t->type_label }}
            </span>
          </td>
          <td><strong>{{ number_format($t->quantity, 0) }}</strong></td>
          <td class="text-muted">{{ number_format($t->stock_before, 0) }}</td>
          <td>{{ number_format($t->stock_after, 0) }}</td>
          <td class="font-mono text-sm text-muted">{{ $t->reference_no ?: '-' }}</td>
          <td class="text-muted text-sm">{{ Str::limit($t->notes, 30) ?: '-' }}</td>
          <td class="text-sm">{{ $t->user?->name ?? '-' }}</td>
        </tr>
        @empty
        <tr><td colspan="10" style="text-align:center;padding:40px;color:var(--text-muted);">Belum ada transaksi</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if($transactions->hasPages())
  <div style="padding:0 16px;">{{ $transactions->links('vendor.pagination.simple-default') }}</div>
  @endif
</div>
@endsection
