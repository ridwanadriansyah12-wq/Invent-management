@extends('layouts.app')
@section('title', 'Laporan & Export')

@section('content')
<div class="page-header">
  <h1 class="page-title">Laporan & Export</h1>
  <p class="page-sub">Unduh data inventaris dalam format CSV</p>
</div>

<div class="d-flex gap-4" style="flex-wrap:wrap;">
  {{-- Stock Report --}}
  <div class="card flex-1" style="min-width:280px;">
    <div class="card-header"><span class="card-title">📊 Laporan Stok Barang</span></div>
    <div class="card-body">
      <p style="color:var(--text-secondary);font-size:13px;margin-bottom:16px;">
        Export semua barang aktif beserta nilai ML (SS, ROP, Max, Demand Type, CV, dll.)
      </p>
      <div style="background:var(--bg-elevated);border:1px solid var(--border);border-radius:6px;padding:12px;margin-bottom:16px;font-size:12px;color:var(--text-muted);">
        <strong style="color:var(--text-secondary);">Kolom yang diekspor:</strong><br>
        Kode · Nama · Kategori · Supplier · Satuan · Stok · SS · ROP · Max · Avg Usage · Planning Usage · Demand Type · CV · Lead Time · Coverage Days · IP · Status
      </div>
      <a href="{{ route('reports.export-stock') }}" class="btn btn-primary w-100">
        ⬇️ Download Laporan Stok (CSV)
      </a>
    </div>
  </div>

  {{-- Transaction Report --}}
  <div class="card flex-1" style="min-width:280px;">
    <div class="card-header"><span class="card-title">📋 Laporan Transaksi</span></div>
    <div class="card-body">
      <p style="color:var(--text-secondary);font-size:13px;margin-bottom:16px;">
        Export riwayat transaksi stok masuk/keluar dalam rentang tanggal tertentu.
      </p>
      <form action="{{ route('reports.export-transactions') }}" method="GET">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Dari Tanggal</label>
            <input type="date" name="from" class="form-control" value="{{ date('Y-m-01') }}">
          </div>
          <div class="form-group">
            <label class="form-label">Sampai Tanggal</label>
            <input type="date" name="to" class="form-control" value="{{ date('Y-m-d') }}">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Tipe Transaksi</label>
          <select name="type" class="form-control">
            <option value="">Semua</option>
            <option value="in">Stok Masuk</option>
            <option value="out">Stok Keluar</option>
            <option value="adjustment">Penyesuaian</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary w-100">⬇️ Download Laporan Transaksi (CSV)</button>
      </form>
    </div>
  </div>
</div>
@endsection
