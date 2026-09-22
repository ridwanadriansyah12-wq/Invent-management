@extends('layouts.app')
@section('title', 'Detail PO ' . $purchaseOrder->po_number)

@section('content')
<div class="page-header">
  <div class="breadcrumb"><a href="{{ route('purchase-orders.index') }}">Purchase Order</a> <span class="breadcrumb-sep">/</span> <span>{{ $purchaseOrder->po_number }}</span></div>
  <div class="d-flex align-center justify-between">
    <h1 class="page-title">{{ $purchaseOrder->po_number }}</h1>
    <span class="badge {{ $purchaseOrder->status_badge }}" style="font-size:14px;padding:6px 14px;">{{ $purchaseOrder->status_label }}</span>
  </div>
</div>

<div class="d-flex gap-4" style="flex-wrap:wrap;align-items:flex-start;">
  {{-- PO Detail --}}
  <div class="card flex-1" style="min-width:300px;">
    <div class="card-header"><span class="card-title">Detail Purchase Order</span></div>
    <div class="card-body">
      <table style="width:100%;font-size:13px;">
        <tr><td style="padding:8px 0;color:var(--text-muted);width:40%;">No PO</td><td class="font-mono"><strong>{{ $purchaseOrder->po_number }}</strong></td></tr>
        <tr><td style="padding:8px 0;color:var(--text-muted);">Barang</td>
          <td>
            <a href="{{ route('items.show', $purchaseOrder->item_id) }}" style="color:var(--text-primary);font-weight:600;">
              [{{ $purchaseOrder->item?->code }}] {{ $purchaseOrder->item?->name }}
            </a>
          </td>
        </tr>
        <tr><td style="padding:8px 0;color:var(--text-muted);">Supplier</td><td>{{ $purchaseOrder->supplier?->name ?? '-' }}</td></tr>
        <tr><td style="padding:8px 0;color:var(--text-muted);">Quantity</td><td><strong>{{ number_format($purchaseOrder->quantity, 0) }}</strong> {{ $purchaseOrder->item?->unit }}</td></tr>
        <tr><td style="padding:8px 0;color:var(--text-muted);">Diterima</td><td>{{ number_format($purchaseOrder->quantity_received, 0) }} {{ $purchaseOrder->item?->unit }}</td></tr>
        <tr><td style="padding:8px 0;color:var(--text-muted);">Sisa</td><td>{{ number_format($purchaseOrder->quantity - $purchaseOrder->quantity_received, 0) }} {{ $purchaseOrder->item?->unit }}</td></tr>
        <tr><td style="padding:8px 0;color:var(--text-muted);">Tgl Order</td><td>{{ $purchaseOrder->order_date->format('d/m/Y') }}</td></tr>
        <tr><td style="padding:8px 0;color:var(--text-muted);">Exp. Tiba</td><td>{{ $purchaseOrder->expected_date?->format('d/m/Y') ?? '-' }}</td></tr>
        <tr><td style="padding:8px 0;color:var(--text-muted);">Tgl Diterima</td><td>{{ $purchaseOrder->received_date?->format('d/m/Y') ?? '-' }}</td></tr>
        <tr><td style="padding:8px 0;color:var(--text-muted);">Dibuat oleh</td><td>{{ $purchaseOrder->user?->name }}</td></tr>
        <tr><td style="padding:8px 0;color:var(--text-muted);">Catatan</td><td>{{ $purchaseOrder->notes ?: '-' }}</td></tr>
      </table>
    </div>
  </div>

  {{-- Actions --}}
  @if(in_array($purchaseOrder->status, ['pending','partial']))
  <div style="min-width:260px;max-width:320px;display:flex;flex-direction:column;gap:12px;">
    <div class="card">
      <div class="card-header"><span class="card-title">Terima Barang</span></div>
      <div class="card-body">
        <form action="{{ route('purchase-orders.receive', $purchaseOrder) }}" method="POST">
          @csrf
          <div class="form-group">
            <label class="form-label">Jumlah Diterima <span class="required">*</span></label>
            @php $remaining = $purchaseOrder->quantity - $purchaseOrder->quantity_received; @endphp
            <input type="number" name="quantity_received" class="form-control"
              value="{{ $remaining }}" min="0.01" max="{{ $remaining }}" step="0.01" required>
            <div class="form-hint">Maks: {{ number_format($remaining, 0) }} {{ $purchaseOrder->item?->unit }}</div>
          </div>
          <div class="form-group">
            <label class="form-label">Catatan</label>
            <textarea name="notes" class="form-control" rows="2" placeholder="Opsional..."></textarea>
          </div>
          <button type="submit" class="btn btn-success w-100">✅ Konfirmasi Penerimaan</button>
        </form>
      </div>
    </div>

    <form action="{{ route('purchase-orders.cancel', $purchaseOrder) }}" method="POST">
      @csrf
      <button type="button" class="btn btn-danger w-100" data-delete
        data-delete-title="Batalkan PO"
        data-delete-msg="Batalkan PO {{ $purchaseOrder->po_number }}? Stock on order akan dikurangi.">
        ❌ Batalkan PO
      </button>
    </form>
  </div>
  @endif
</div>
@endsection
