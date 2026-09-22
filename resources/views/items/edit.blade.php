@extends('layouts.app')
@section('title', 'Edit Barang')

@section('content')
<div class="page-header">
  <div class="breadcrumb">
    <a href="{{ route('items.index') }}">Barang</a>
    <span class="breadcrumb-sep">/</span>
    <a href="{{ route('items.show', $item) }}">{{ $item->code }}</a>
    <span class="breadcrumb-sep">/</span>
    <span>Edit</span>
  </div>
  <h1 class="page-title">Edit Barang</h1>
</div>

<form action="{{ route('items.update', $item) }}" method="POST">
@csrf @method('PUT')
<div class="d-flex gap-4" style="flex-wrap:wrap;align-items:flex-start;">

  <div class="card flex-1" style="min-width:300px;">
    <div class="card-header"><span class="card-title">Informasi Barang</span></div>
    <div class="card-body">
      <div class="form-row">
        <div class="form-group">
          <label for="item_code" class="form-label">Kode Barang <span class="required">*</span></label>
          <input type="text" id="item_code" name="code" class="form-control {{ $errors->has('code') ? 'is-invalid' : '' }}"
            value="{{ old('code', $item->code) }}" required maxlength="50">
          @error('code') <div class="form-error">{{ $message }}</div> @enderror
        </div>
        <div class="form-group">
          <label for="unit" class="form-label">Satuan <span class="required">*</span></label>
          <input type="text" id="unit" name="unit" class="form-control"
            value="{{ old('unit', $item->unit) }}" required maxlength="30">
        </div>
      </div>

      <div class="form-group">
        <label for="name" class="form-label">Nama Barang <span class="required">*</span></label>
        <input type="text" id="name" name="name" class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}"
          value="{{ old('name', $item->name) }}" required maxlength="200">
        @error('name') <div class="form-error">{{ $message }}</div> @enderror
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Kategori <span class="required">*</span></label>
          <select name="category_id" class="form-control" required>
            @foreach($categories as $cat)
              <option value="{{ $cat->id }}" {{ old('category_id', $item->category_id) == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Supplier</label>
          <select name="supplier_id" class="form-control">
            <option value="">-- Tanpa Supplier --</option>
            @foreach($suppliers as $s)
              <option value="{{ $s->id }}" {{ old('supplier_id', $item->supplier_id) == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
            @endforeach
          </select>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Deskripsi</label>
        <textarea name="description" class="form-control" rows="3">{{ old('description', $item->description) }}</textarea>
      </div>

      <div class="metric-box">
        <div class="metric-label">Stok Saat Ini</div>
        <div class="metric-value">{{ number_format($item->stock_on_hand, 0) }}</div>
        <div class="metric-unit">{{ $item->unit }} (tidak bisa diubah langsung – gunakan Transaksi)</div>
      </div>
    </div>
  </div>

  <div style="min-width:280px;max-width:340px;display:flex;flex-direction:column;gap:16px;">
    <div class="card">
      <div class="card-header"><span class="card-title">🧠 Parameter ML</span></div>
      <div class="card-body">
        <div class="form-group">
          <label class="form-label">Lead Time (hari) <span class="required">*</span></label>
          <input type="number" name="lead_time_days" class="form-control"
            value="{{ old('lead_time_days', $item->lead_time_days) }}" min="1" max="365" required>
          @error('lead_time_days') <div class="form-error">{{ $message }}</div> @enderror
        </div>
        <div class="form-group">
          <label class="form-label">Coverage Period (hari) <span class="required">*</span></label>
          <input type="number" name="coverage_period" class="form-control"
            value="{{ old('coverage_period', $item->coverage_period) }}" min="1" max="365" required>
          @error('coverage_period') <div class="form-error">{{ $message }}</div> @enderror
        </div>

        <hr class="divider">

        <div class="d-flex gap-2 flex-wrap">
          @foreach([['Safety Stock', $item->safety_stock], ['ROP', $item->rop], ['Max Stok', $item->max_stock]] as [$label, $val])
          <div style="flex:1;min-width:80px;background:var(--bg-elevated);border:1px solid var(--border);border-radius:6px;padding:10px;text-align:center;">
            <div style="font-size:10px;color:var(--text-muted);text-transform:uppercase;margin-bottom:4px;">{{ $label }}</div>
            <div style="font-size:16px;font-weight:700;">{{ number_format($val, 1) }}</div>
          </div>
          @endforeach
        </div>

        <div class="form-hint" style="margin-top:10px;">Nilai ML akan dihitung ulang setelah simpan.</div>
      </div>
    </div>

    <div class="d-flex gap-3">
      <a href="{{ route('items.show', $item) }}" class="btn btn-secondary flex-1">Batal</a>
      <button type="submit" class="btn btn-primary flex-1">Simpan Perubahan</button>
    </div>
  </div>

</div>
</form>
@endsection
