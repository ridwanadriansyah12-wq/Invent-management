@extends('layouts.app')
@section('title', 'Input Transaksi')

@section('content')
<div class="page-header">
  <div class="breadcrumb">
    <a href="{{ route('transactions.index') }}">Transaksi</a>
    <span class="breadcrumb-sep">/</span><span>Input</span>
  </div>
  <h1 class="page-title">Input Transaksi Stok</h1>
</div>

@php
  $itemsData = $items->mapWithKeys(fn($i) => [$i->id => [
    'stock_on_hand'        => $i->stock_on_hand,
    'unit'                 => $i->unit,
    'rop'                  => $i->rop,
    'max_stock'            => $i->max_stock,
    'safety_stock'         => $i->safety_stock,
    'recommended_order_qty'=> $i->recommended_order_qty,
    'supplier_id'          => $i->supplier_id,
  ]]);
@endphp

<script>window.ITEMS_DATA = @json($itemsData);</script>

<div class="d-flex gap-4" style="flex-wrap:wrap;align-items:flex-start;">

  <div class="card flex-1" style="min-width:300px;">
    <div class="card-header"><span class="card-title">Form Transaksi</span></div>
    <div class="card-body">
      <form action="{{ route('transactions.store') }}" method="POST">
        @csrf

        <div class="form-group">
          <label class="form-label">Barang <span class="required">*</span></label>
          <select id="item_select" name="item_id" class="form-control {{ $errors->has('item_id') ? 'is-invalid' : '' }}" required>
            <option value="">-- Pilih Barang --</option>
            @foreach($items as $item)
              <option value="{{ $item->id }}" {{ old('item_id', $selectedItem?->id) == $item->id ? 'selected' : '' }}>
                [{{ $item->code }}] {{ $item->name }}
              </option>
            @endforeach
          </select>
          @error('item_id') <div class="form-error">{{ $message }}</div> @enderror
        </div>

        <div class="form-group">
          <label class="form-label">Tipe Transaksi <span class="required">*</span></label>
          <select name="type" class="form-control" required>
            <option value="in"         {{ old('type') === 'in'         ? 'selected' : '' }}>📥 Stok Masuk</option>
            <option value="out"        {{ old('type') === 'out'        ? 'selected' : '' }}>📤 Stok Keluar</option>
            <option value="adjustment" {{ old('type') === 'adjustment' ? 'selected' : '' }}>⚙️ Penyesuaian Stok</option>
          </select>
        </div>

        <div class="form-group">
          <label for="quantity" class="form-label">Jumlah <span class="required">*</span></label>
          <input type="number" id="quantity" name="quantity" class="form-control {{ $errors->has('quantity') ? 'is-invalid' : '' }}"
            value="{{ old('quantity') }}" min="0.01" step="0.01" required placeholder="0">
          <span id="max_stock_val" style="display:none">{{ $selectedItem?->stock_on_hand ?? 0 }}</span>
          @error('quantity') <div class="form-error">{{ $message }}</div> @enderror
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Tanggal <span class="required">*</span></label>
            <input type="date" name="transaction_date" class="form-control"
              value="{{ old('transaction_date', date('Y-m-d')) }}" required>
            @error('transaction_date') <div class="form-error">{{ $message }}</div> @enderror
          </div>
          <div class="form-group">
            <label class="form-label">No. Referensi</label>
            <input type="text" name="reference_no" class="form-control" placeholder="No. DO / SO / PO"
              value="{{ old('reference_no') }}" maxlength="100">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Catatan</label>
          <textarea name="notes" class="form-control" rows="2" placeholder="Keterangan tambahan...">{{ old('notes') }}</textarea>
        </div>

        <div class="d-flex gap-3">
          <a href="{{ route('transactions.index') }}" class="btn btn-secondary flex-1">Batal</a>
          <button type="submit" class="btn btn-primary flex-1">Simpan Transaksi</button>
        </div>
      </form>
    </div>
  </div>

  {{-- Info Panel --}}
  <div style="min-width:240px;max-width:300px;display:flex;flex-direction:column;gap:12px;">
    <div class="card">
      <div class="card-header"><span class="card-title">Info Barang</span></div>
      <div class="card-body">
        <div class="d-flex justify-between align-center mb-2">
          <span class="text-muted text-sm">Stok Saat Ini</span>
          <strong id="current_stock">–</strong>
        </div>
        <div class="d-flex justify-between align-center mb-2">
          <span class="text-muted text-sm">ROP</span>
          <span id="item_rop">–</span>
        </div>
        <div class="d-flex justify-between align-center mb-2">
          <span class="text-muted text-sm">Max Stok</span>
          <span id="item_max">–</span>
        </div>
        <hr class="divider">
        <div class="text-muted text-sm">Rec. Order Qty</div>
        <div style="font-size:20px;font-weight:700;margin-top:4px;" id="suggested_qty">–</div>
      </div>
    </div>
    <div class="metric-box">
      <div class="metric-label">ℹ️ Catatan</div>
      <div style="font-size:12px;color:var(--text-secondary);margin-top:6px;">
        Setelah transaksi disimpan, ML Engine akan otomatis menghitung ulang SS, ROP, dan Max Stok barang ini.
      </div>
    </div>
  </div>
</div>
@endsection
