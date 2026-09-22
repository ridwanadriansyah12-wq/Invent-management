@extends('layouts.app')
@section('title', 'Tambah Barang')

@section('content')
<div class="page-header">
  <div class="breadcrumb">
    <a href="{{ route('items.index') }}">Barang</a>
    <span class="breadcrumb-sep">/</span>
    <span>Tambah</span>
  </div>
  <h1 class="page-title">Tambah Barang Baru</h1>
</div>

<form action="{{ route('items.store') }}" method="POST">
@csrf
<div class="d-flex gap-4" style="flex-wrap:wrap;align-items:flex-start;">

  {{-- Left: Main Info --}}
  <div class="card flex-1" style="min-width:300px;">
    <div class="card-header"><span class="card-title">Informasi Barang</span></div>
    <div class="card-body">
      <div class="form-row">
        <div class="form-group">
          <label for="item_code" class="form-label">Kode Barang <span class="required">*</span></label>
          <input type="text" id="item_code" name="code" class="form-control {{ $errors->has('code') ? 'is-invalid' : '' }}"
            placeholder="CTH-001" value="{{ old('code') }}" required maxlength="50">
          @error('code') <div class="form-error">{{ $message }}</div> @enderror
          <div class="form-hint">Kode unik, otomatis diubah ke huruf kapital</div>
        </div>
        <div class="form-group">
          <label for="unit" class="form-label">Satuan <span class="required">*</span></label>
          <input type="text" id="unit" name="unit" class="form-control {{ $errors->has('unit') ? 'is-invalid' : '' }}"
            placeholder="pcs, kg, liter..." value="{{ old('unit', 'pcs') }}" required maxlength="30">
          @error('unit') <div class="form-error">{{ $message }}</div> @enderror
        </div>
      </div>

      <div class="form-group">
        <label for="name" class="form-label">Nama Barang <span class="required">*</span></label>
        <input type="text" id="name" name="name" class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}"
          placeholder="Nama lengkap barang" value="{{ old('name') }}" required maxlength="200">
        @error('name') <div class="form-error">{{ $message }}</div> @enderror
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="category_id" class="form-label">Kategori <span class="required">*</span></label>
          <select id="category_id" name="category_id" class="form-control {{ $errors->has('category_id') ? 'is-invalid' : '' }}" required>
            <option value="">-- Pilih Kategori --</option>
            @foreach($categories as $cat)
              <option value="{{ $cat->id }}" {{ old('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
            @endforeach
          </select>
          @error('category_id') <div class="form-error">{{ $message }}</div> @enderror
        </div>
        <div class="form-group">
          <label for="supplier_id" class="form-label">Supplier</label>
          <select id="supplier_id" name="supplier_id" class="form-control">
            <option value="">-- Tanpa Supplier --</option>
            @foreach($suppliers as $s)
              <option value="{{ $s->id }}" {{ old('supplier_id') == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
            @endforeach
          </select>
        </div>
      </div>

      <div class="form-group">
        <label for="description" class="form-label">Deskripsi</label>
        <textarea id="description" name="description" class="form-control" rows="3" placeholder="Keterangan tambahan...">{{ old('description') }}</textarea>
      </div>

      <div class="form-group">
        <label for="stock_on_hand" class="form-label">Stok Awal <span class="required">*</span></label>
        <input type="number" id="stock_on_hand" name="stock_on_hand" class="form-control {{ $errors->has('stock_on_hand') ? 'is-invalid' : '' }}"
          value="{{ old('stock_on_hand', 0) }}" min="0" step="0.01" required>
        @error('stock_on_hand') <div class="form-error">{{ $message }}</div> @enderror
      </div>
    </div>
  </div>

  {{-- Right: ML Parameters --}}
  <div style="min-width:280px;max-width:340px;display:flex;flex-direction:column;gap:16px;">
    <div class="card">
      <div class="card-header"><span class="card-title">🧠 Parameter ML</span></div>
      <div class="card-body">
        <div class="form-group">
          <label for="lead_time_days" class="form-label">Lead Time <span class="required">*</span></label>
          <div class="input-group">
            <input type="number" id="lead_time_days" name="lead_time_days" class="form-control"
              value="{{ old('lead_time_days', 7) }}" min="1" max="365" required>
          </div>
          <div class="form-hint">Hari dari order hingga barang tiba</div>
          @error('lead_time_days') <div class="form-error">{{ $message }}</div> @enderror
        </div>

        <div class="form-group">
          <label for="coverage_period" class="form-label">Coverage Period <span class="required">*</span></label>
          <input type="number" id="coverage_period" name="coverage_period" class="form-control"
            value="{{ old('coverage_period', 30) }}" min="1" max="365" required>
          <div class="form-hint">Hari ketahanan stok yang diinginkan (untuk MAX)</div>
          @error('coverage_period') <div class="form-error">{{ $message }}</div> @enderror
        </div>

        <div class="metric-box" style="margin-top:8px;">
          <div class="metric-label">Formula yang akan dipakai</div>
          <div style="font-size:12px;color:var(--text-secondary);margin-top:6px;">
            SS = (MaxUsage – Avg) × LeadTime/30<br>
            ROP = LTD + SS<br>
            MAX = Planning × Coverage + SS
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-body">
        <div class="alert alert-info" style="margin-bottom:0;">
          <span>ℹ️</span>
          <span style="font-size:12px;">ROP, Safety Stock, dan Max akan dihitung otomatis oleh ML Engine setelah ada data transaksi.</span>
        </div>
      </div>
    </div>

    <div class="d-flex gap-3">
      <a href="{{ route('items.index') }}" class="btn btn-secondary flex-1">Batal</a>
      <button type="submit" class="btn btn-primary flex-1">Simpan Barang</button>
    </div>
  </div>

</div>
</form>
@endsection
