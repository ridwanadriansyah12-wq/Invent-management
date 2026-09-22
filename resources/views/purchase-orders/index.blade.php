@extends('layouts.app')
@section('title', 'Purchase Order')

@section('content')
<div class="page-header d-flex align-center justify-between">
  <div><h1 class="page-title">Purchase Order</h1><p class="page-sub">Manajemen pengadaan barang</p></div>
  <a href="{{ route('purchase-orders.create') }}" class="btn btn-primary">+ Buat PO</a>
</div>

<form method="GET" class="filter-bar mb-4">
  <select name="status" class="form-control" style="max-width:180px;">
    <option value="">Semua Status</option>
    <option value="pending"  {{ request('status') === 'pending'   ? 'selected' : '' }}>Menunggu</option>
    <option value="partial"  {{ request('status') === 'partial'   ? 'selected' : '' }}>Sebagian</option>
    <option value="received" {{ request('status') === 'received'  ? 'selected' : '' }}>Diterima</option>
    <option value="cancelled"{{ request('status') === 'cancelled' ? 'selected' : '' }}>Dibatalkan</option>
  </select>
  <button type="submit" class="btn btn-secondary">Filter</button>
  @if(request('status'))<a href="{{ route('purchase-orders.index') }}" class="btn btn-secondary">Reset</a>@endif
</form>

<div class="card">
  <div class="table-container">
    <table>
      <thead>
        <tr><th>No PO</th><th>Barang</th><th>Supplier</th><th>Qty</th><th>Diterima</th><th>Status</th><th>Tgl Order</th><th>Exp. Date</th><th>Aksi</th></tr>
      </thead>
      <tbody>
        @forelse($pos as $po)
        <tr>
          <td><a href="{{ route('purchase-orders.show', $po) }}" class="font-mono text-sm" style="color:var(--text-primary);">{{ $po->po_number }}</a></td>
          <td>
            <div class="item-code">{{ $po->item?->code }}</div>
            <div class="item-name">{{ $po->item?->name }}</div>
          </td>
          <td class="text-muted text-sm">{{ $po->supplier?->name ?? '-' }}</td>
          <td>{{ number_format($po->quantity, 0) }} {{ $po->item?->unit }}</td>
          <td>{{ number_format($po->quantity_received, 0) }}</td>
          <td><span class="badge {{ $po->status_badge }}">{{ $po->status_label }}</span></td>
          <td class="no-wrap text-sm">{{ $po->order_date->format('d/m/Y') }}</td>
          <td class="no-wrap text-sm text-muted">{{ $po->expected_date?->format('d/m/Y') ?? '-' }}</td>
          <td>
            <div class="d-flex gap-1">
              <a href="{{ route('purchase-orders.show', $po) }}" class="btn btn-sm btn-secondary">Detail</a>
              @if(in_array($po->status, ['pending','partial']))
              <form action="{{ route('purchase-orders.cancel', $po) }}" method="POST">
                @csrf
                <button type="button" class="btn btn-sm btn-danger" data-delete data-delete-title="Batalkan PO" data-delete-msg="Batalkan PO {{ $po->po_number }}?">Batal</button>
              </form>
              @endif
            </div>
          </td>
        </tr>
        @empty
        <tr><td colspan="9" style="text-align:center;padding:40px;color:var(--text-muted);">Belum ada Purchase Order</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if($pos->hasPages())
  <div style="padding:0 16px;">{{ $pos->links('vendor.pagination.simple-default') }}</div>
  @endif
</div>
@endsection
