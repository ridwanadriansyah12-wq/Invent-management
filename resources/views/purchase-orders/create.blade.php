@extends('layouts.app')
@section('title', 'Buat Purchase Order')

@section('content')
<div class="page-header">
  <div class="breadcrumb"><a href="{{ route('purchase-orders.index') }}">Purchase Order</a> <span class="breadcrumb-sep">/</span> <span>Buat</span></div>
  <h1 class="page-title">Buat Purchase Order</h1>
</div>

@php
  $itemsData = $items->mapWithKeys(fn($i) => [$i->id => [
    'stock_on_hand'        => $i->stock_on_hand,
    'unit'                 => $i->unit,
    'rop'                  => $i->rop,
    'max_stock'            => $i->max_stock,
    'recommended_order_qty'=> $i->recommended_order_qty,
    'supplier_id'          => $i->supplier_id,
  ]]);
@endphp
<script>window.ITEMS_DATA = @json($itemsData);</script>

<div class="d-flex gap-4" style="flex-wrap:wrap;align-items:flex-start;">
  <div class="card flex-1" style="min-width:300px;">
    <div class="card-header"><span class="card-title">Detail PO</span></div>
    <div class="card-body">
      <form action="{{ route('purchase-orders.store') }}" method="POST">
        @csrf
        <div class="form-group">
          <label class="form-label">Barang <span class="required">*</span></label>
          <select id="item_select" name="item_id" class="form-control" required>
            <option value="">-- Pilih Barang --</option>
            @foreach($items as $item)
              <option value="{{ $item->id }}" {{ (old('item_id', $preItem?->id) == $item->id) ? 'selected' : '' }}>
                [{{ $item->code }}] {{ $item->name }}
              </option>
            @endforeach
          </select>
          @error('item_id') <div class="form-error">{{ $message }}</div> @enderror
        </div>

        <div class="form-group">
          <label class="form-label">Supplier</label>
          <select id="supplier_id" name="supplier_id" class="form-control">
            <option value="">-- Pilih Supplier --</option>
            @foreach($suppliers as $s)
              <option value="{{ $s->id }}" {{ old('supplier_id') == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
            @endforeach
          </select>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Jumlah Order <span class="required">*</span></label>
            <input type="number" name="quantity" class="form-control" value="{{ old('quantity') }}"
              min="0.01" step="0.01" required placeholder="0">
            @error('quantity') <div class="form-error">{{ $message }}</div> @enderror
            <div class="form-hint">Rec: <span id="suggested_qty">–</span></div>
          </div>
          <div class="form-group">
            <label class="form-label">Tgl Order <span class="required">*</span></label>
            <input type="date" name="order_date" class="form-control" value="{{ old('order_date', date('Y-m-d')) }}" required>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Estimasi Tgl Tiba</label>
          <input type="date" name="expected_date" class="form-control" value="{{ old('expected_date') }}">
        </div>

        <div class="form-group">
          <label class="form-label">Catatan</label>
          <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
        </div>

        <div class="d-flex gap-3">
          <a href="{{ route('purchase-orders.index') }}" class="btn btn-secondary flex-1">Batal</a>
          <button type="submit" class="btn btn-primary flex-1">Buat PO</button>
        </div>
      </form>
    </div>
  </div>

  <div style="min-width:240px;max-width:300px;">
    <div class="card">
      <div class="card-header"><span class="card-title">Info Barang</span></div>
      <div class="card-body">
        <div class="d-flex justify-between mb-2"><span class="text-muted text-sm">Stok</span><strong id="current_stock">–</strong></div>
        <div class="d-flex justify-between mb-2"><span class="text-muted text-sm">ROP</span><span id="item_rop">–</span></div>
        <div class="d-flex justify-between mb-2"><span class="text-muted text-sm">Max Stok</span><span id="item_max">–</span></div>
        <hr class="divider">
        <div class="text-muted text-sm">Rec. Order Qty</div>
        <div style="font-size:24px;font-weight:700;margin-top:4px;" id="suggested_qty_large">–</div>
      </div>
    </div>
  </div>
</div>
@endsection
