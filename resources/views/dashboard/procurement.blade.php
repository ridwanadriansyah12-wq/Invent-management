@extends('layouts.app')
@section('title', 'Dashboard – Procurement')

@push('styles')
<style>
  .reorder-table tr td:nth-child(5) { font-variant-numeric: tabular-nums; }
</style>
@endpush

@section('content')
<div class="page-header">
  <h1 class="page-title">Dashboard Procurement</h1>
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
    <div class="stat-icon info">🛒</div>
    <div class="stat-info"><div class="stat-value">{{ $pendingPO + $partialPO }}</div><div class="stat-label">PO Aktif</div></div>
  </div>
</div>

{{-- Charts Row --}}
<div class="d-flex gap-4 mb-5" style="flex-wrap:wrap;">
  {{-- Top Usage --}}
  <div class="card flex-1" style="min-width:300px;">
    <div class="card-header">
      <span class="card-title">Top 10 Barang Terpakai (3 Bulan)</span>
    </div>
    <div class="card-body">
      <div class="chart-container">
        <canvas id="topUsageChart"></canvas>
      </div>
    </div>
  </div>

  {{-- Monthly Trend --}}
  <div class="card flex-1" style="min-width:300px;">
    <div class="card-header">
      <span class="card-title">Tren Pemakaian Bulanan (6 Bulan)</span>
    </div>
    <div class="card-body">
      <div class="chart-container">
        <canvas id="trendChart"></canvas>
      </div>
    </div>
  </div>
</div>

{{-- Reorder Alert Table --}}
@if($reorderItems->count())
<div class="card mb-5">
  <div class="card-header">
    <span class="card-title">🔄 Barang Perlu Reorder (IP ≤ ROP)</span>
    <a href="{{ route('purchase-orders.create') }}" class="btn btn-sm btn-warning">+ Buat PO</a>
  </div>
  <div class="table-container">
    <table>
      <thead>
        <tr>
          <th>Barang</th><th>Stok</th><th>IP</th><th>ROP</th><th>Safety Stock</th>
          <th>Rec. Order</th><th>Days to ROP</th><th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        @foreach($reorderItems as $item)
        <tr class="{{ $item->stock_on_hand <= 0 ? 'row-critical' : 'row-warning' }}">
          <td>
            <div class="item-code">{{ $item->code }}</div>
            <div class="item-name">{{ $item->name }}</div>
            <div class="text-sm text-muted">{{ $item->category?->name }}</div>
          </td>
          <td>
            <span class="{{ $item->stock_on_hand <= 0 ? 'badge badge-danger' : 'badge badge-warning' }}">
              {{ number_format($item->stock_on_hand, 0) }} {{ $item->unit }}
            </span>
          </td>
          <td>{{ number_format($item->inventory_position, 0) }}</td>
          <td>{{ number_format($item->rop, 1) }}</td>
          <td>{{ number_format($item->safety_stock, 1) }}</td>
          <td><strong>{{ number_format($item->recommended_order_qty, 0) }}</strong> {{ $item->unit }}</td>
          <td>
            @if($item->days_to_rop == 0)
              <span class="badge badge-danger">Sudah lewat</span>
            @else
              <span class="badge badge-warning">{{ $item->days_to_rop }} hari</span>
            @endif
          </td>
          <td>
            <a href="{{ route('purchase-orders.create', ['item_id' => $item->id]) }}" class="btn btn-sm btn-warning">Order</a>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endif

{{-- Two column: Out of stock + Recent PO --}}
<div class="d-flex gap-4" style="flex-wrap:wrap;">
  {{-- Out of stock --}}
  @if($outItems->count())
  <div class="card flex-1" style="min-width:280px;">
    <div class="card-header">
      <span class="card-title">⛔ Stok Habis</span>
      <span class="badge badge-danger">{{ $outItems->count() }} barang</span>
    </div>
    <div class="table-container">
      <table>
        <thead><tr><th>Barang</th><th>Max Stock</th><th>Aksi</th></tr></thead>
        <tbody>
          @foreach($outItems->take(8) as $item)
          <tr class="row-critical">
            <td>
              <div class="item-code">{{ $item->code }}</div>
              <div class="item-name">{{ $item->name }}</div>
            </td>
            <td>{{ number_format($item->max_stock, 0) }} {{ $item->unit }}</td>
            <td><a href="{{ route('purchase-orders.create', ['item_id' => $item->id]) }}" class="btn btn-sm btn-danger">Order</a></td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
  @endif

  {{-- Recent POs --}}
  <div class="card flex-1" style="min-width:280px;">
    <div class="card-header">
      <span class="card-title">PO Terbaru</span>
      <a href="{{ route('purchase-orders.index') }}" class="btn btn-sm btn-secondary">Lihat Semua</a>
    </div>
    <div class="table-container">
      <table>
        <thead><tr><th>No PO</th><th>Barang</th><th>Status</th></tr></thead>
        <tbody>
          @forelse($recentPOs as $po)
          <tr>
            <td><span class="font-mono text-sm">{{ $po->po_number }}</span></td>
            <td>{{ $po->item?->name ?? '-' }}</td>
            <td><span class="badge {{ $po->status_badge }}">{{ $po->status_label }}</span></td>
          </tr>
          @empty
          <tr><td colspan="3" class="text-muted" style="text-align:center;padding:20px;">Belum ada PO</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
const topUsageData = @json($topItems);
const trendData    = @json($monthlyTrend);

// Top Usage Bar Chart
new Chart(document.getElementById('topUsageChart'), {
  type: 'bar',
  data: {
    labels: topUsageData.map(d => d.item ? (d.item.code + ' – ' + d.item.name.substring(0,15)) : '?'),
    datasets: [{
      label: 'Total Keluar',
      data: topUsageData.map(d => d.total),
      backgroundColor: 'rgba(255,255,255,0.15)',
      borderColor: 'rgba(255,255,255,0.6)',
      borderWidth: 1,
      borderRadius: 4,
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    indexAxis: 'y',
    plugins: { legend: { display: false } },
    scales: {
      x: { grid: { color: '#2a2a2a' }, ticks: { color: '#a0a0a0' } },
      y: { grid: { display: false }, ticks: { color: '#f5f5f5', font: { size: 11 } } }
    }
  }
});

// Trend Line Chart
new Chart(document.getElementById('trendChart'), {
  type: 'line',
  data: {
    labels: trendData.map(d => d.label),
    datasets: [{
      label: 'Total Pemakaian',
      data: trendData.map(d => d.total),
      borderColor: '#ffffff',
      backgroundColor: 'rgba(255,255,255,0.05)',
      fill: true,
      tension: 0.4,
      pointBackgroundColor: '#ffffff',
      pointRadius: 4,
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      x: { grid: { color: '#2a2a2a' }, ticks: { color: '#a0a0a0' } },
      y: { grid: { color: '#1a1a1a' }, ticks: { color: '#a0a0a0' } }
    }
  }
});
</script>
@endpush
