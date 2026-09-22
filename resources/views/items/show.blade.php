@extends('layouts.app')
@section('title', $item->name)

@section('content')
<div class="page-header">
  <div class="breadcrumb">
    <a href="{{ route('items.index') }}">Barang</a>
    <span class="breadcrumb-sep">/</span>
    <span>{{ $item->code }}</span>
  </div>
  <div class="d-flex align-center justify-between" style="flex-wrap:wrap;gap:12px;">
    <div>
      <h1 class="page-title">{{ $item->name }}</h1>
      <p class="page-sub">
        <span class="font-mono">{{ $item->code }}</span> ·
        {{ $item->category?->name ?? '-' }} ·
        {{ $item->unit }}
        @if($item->supplier)· {{ $item->supplier->name }}@endif
      </p>
    </div>
    @if(auth()->user()->isGudang())
    <div class="d-flex gap-2">
      <form action="{{ route('items.recalculate', $item) }}" method="POST">
        @csrf
        <button type="submit" class="btn btn-secondary" title="Paksa hitung ulang ML">🔄 Hitung Ulang ML</button>
      </form>
      <a href="{{ route('items.edit', $item) }}" class="btn btn-secondary">Edit</a>
      <a href="{{ route('transactions.create', ['item_id' => $item->id]) }}" class="btn btn-primary">+ Transaksi</a>
    </div>
    @endif
  </div>
</div>

{{-- Status Banner --}}
@php $status = $item->stock_status; @endphp
@if($status !== 'normal')
<div class="alert {{ in_array($status, ['out_of_stock','critical']) ? 'alert-error' : 'alert-warning' }}" style="margin-bottom:20px;">
  <span>{{ $status === 'out_of_stock' ? '⛔' : '⚠️' }}</span>
  <span>
    @if($status === 'out_of_stock') Stok barang ini <strong>HABIS</strong>. Segera lakukan pengadaan.
    @elseif($status === 'critical') Stok di bawah Safety Stock ({{ number_format($item->safety_stock, 1) }} {{ $item->unit }}). Kondisi kritis!
    @elseif($status === 'low') Inventory Position mencapai ROP ({{ number_format($item->rop, 1) }}). Segera order.
    @endif
  </span>
</div>
@endif

{{-- Main Metrics --}}
<div class="metrics-grid mb-5">
  <div class="metric-box {{ $item->stock_on_hand <= 0 ? 'danger' : ($item->stock_on_hand <= $item->safety_stock ? 'warning' : '') }}">
    <div class="metric-label">Stok On-Hand</div>
    <div class="metric-value">{{ number_format($item->stock_on_hand, 0) }}</div>
    <div class="metric-unit">{{ $item->unit }}</div>
  </div>
  <div class="metric-box">
    <div class="metric-label">Inventory Position (IP)</div>
    <div class="metric-value">{{ number_format($item->inventory_position, 0) }}</div>
    <div class="metric-unit">OnHand + OnOrder – Reserved</div>
  </div>
  <div class="metric-box highlight">
    <div class="metric-label">Safety Stock (SS)</div>
    <div class="metric-value">{{ number_format($item->safety_stock, 1) }}</div>
    <div class="metric-unit">
      {{ $item->unit }}
      @if($item->is_manual_override)
        <span style="color:#d97706;font-weight:600;display:block;font-size:10px;">👤 Manual Override</span>
      @else
        <span style="color:#2563eb;font-weight:600;display:block;font-size:10px;">🤖 ML Prediction</span>
      @endif
    </div>
  </div>
  <div class="metric-box highlight">
    <div class="metric-label">ROP</div>
    <div class="metric-value">{{ number_format($item->rop, 1) }}</div>
    <div class="metric-unit">
      {{ $item->unit }}
      @if($item->is_manual_override)
        <span style="color:#d97706;font-weight:600;display:block;font-size:10px;">👤 Manual Override</span>
      @else
        <span style="color:#2563eb;font-weight:600;display:block;font-size:10px;">🤖 ML Prediction</span>
      @endif
    </div>
  </div>
  <div class="metric-box highlight">
    <div class="metric-label">Max Stock</div>
    <div class="metric-value">{{ number_format($item->max_stock, 1) }}</div>
    <div class="metric-unit">{{ $item->unit }}</div>
  </div>
  <div class="metric-box">
    <div class="metric-label">Coverage Days</div>
    <div class="metric-value">{{ $item->coverage_days }}</div>
    <div class="metric-unit">hari</div>
  </div>
  <div class="metric-box {{ $item->days_to_rop < 7 ? 'warning' : '' }}">
    <div class="metric-label">Days to ROP</div>
    <div class="metric-value">{{ $item->days_to_rop }}</div>
    <div class="metric-unit">hari menuju ROP</div>
  </div>
  <div class="metric-box">
    <div class="metric-label">Rec. Order Qty</div>
    <div class="metric-value">{{ number_format($item->recommended_order_qty, 0) }}</div>
    <div class="metric-unit">{{ $item->unit }} (MAX – IP)</div>
  </div>
</div>

<div class="d-flex gap-4" style="flex-wrap:wrap;">
  {{-- ML Details --}}
  <div class="card" style="min-width:280px;flex:1;">
    <div class="card-header"><span class="card-title">🧠 Detail ML Engine</span></div>
    <div class="card-body">
      <table style="width:100%;font-size:13px;">
        <tr>
          <td style="padding:6px 0;color:var(--text-muted);width:55%;">Demand Type</td>
          <td>
            <span class="badge {{ $item->demand_type === 'intermittent' ? 'badge-info' : 'badge-success' }}">
              {{ ucfirst($item->demand_type) }}
            </span>
          </td>
        </tr>
        <tr>
          <td style="padding:6px 0;color:var(--text-muted);">CV (Coeff. Variation)</td>
          <td><strong>{{ number_format($item->cv_value, 4) }}</strong> {{ $item->cv_value > 0.5 ? '(> 0.5 → Intermittent)' : '(≤ 0.5 → Regular)' }}</td>
        </tr>
        <tr>
          <td style="padding:6px 0;color:var(--text-muted);">Avg Usage (12bln)</td>
          <td><strong>{{ number_format($item->avg_usage, 2) }}</strong> {{ $item->unit }}/bln</td>
        </tr>
        <tr>
          <td style="padding:6px 0;color:var(--text-muted);">Planning Usage</td>
          <td><strong>{{ number_format($item->planning_usage, 2) }}</strong> {{ $item->unit }}/bln</td>
        </tr>
        <tr>
          <td style="padding:6px 0;color:var(--text-muted);">Lead Time</td>
          <td><strong>{{ $item->lead_time_days }}</strong> hari</td>
        </tr>
        <tr>
          <td style="padding:6px 0;color:var(--text-muted);">Coverage Period</td>
          <td><strong>{{ $item->coverage_period }}</strong> hari</td>
        </tr>
        <tr>
          <td style="padding:6px 0;color:var(--text-muted);">On Order</td>
          <td><strong>{{ number_format($item->stock_on_order, 0) }}</strong> {{ $item->unit }}</td>
        </tr>
        <tr>
          <td style="padding:6px 0;color:var(--text-muted);">Last ML Update</td>
          <td>{{ $item->last_ml_update ? $item->last_ml_update->diffForHumans() : '-' }}</td>
        </tr>
      </table>
    </div>
  </div>

  {{-- Monthly Usage Chart --}}
  <div class="card" style="min-width:300px;flex:2;">
    <div class="card-header"><span class="card-title">Pemakaian Bulanan</span></div>
    <div class="card-body">
      @if($item->monthlyUsages->count())
      <div class="chart-container">
        <canvas id="usageChart"></canvas>
      </div>
      @else
      <div class="empty-state" style="padding:30px 0;">
        <div class="empty-icon">📊</div>
        <div class="empty-title">Belum ada data pemakaian</div>
        <div class="empty-desc">Data akan muncul setelah ada transaksi keluar</div>
      </div>
      @endif
    </div>
  </div>
</div>

{{-- Human-in-the-Loop: Manual Override & Redis In-Memory Status --}}
<div class="card mt-4" style="border: 1px solid {{ $item->is_manual_override ? '#f59e0b' : '#e2e8f0' }};">
  <div class="card-header d-flex justify-between align-center" style="background: {{ $item->is_manual_override ? '#fffbeb' : 'inherit' }};">
    <div class="d-flex align-center gap-2">
      <span class="card-title">🎛️ Manual Override Parameter (Human-in-the-Loop)</span>
      @if($item->is_manual_override)
        <span class="badge badge-warning">👤 Mode Manual Override Aktif</span>
      @else
        <span class="badge badge-success">🤖 Mode Prediksi ML Aktif</span>
      @endif
    </div>
    @if(isset($effectiveParams['source']))
      <span style="font-size:12px; color:var(--text-muted);">
        Lookup Layer: 
        @if($effectiveParams['source'] === 'redis')
          <strong style="color:#059669;">⚡ Redis In-Memory Cache</strong>
        @else
          <strong style="color:#64748b;">🗄️ Database Fallback</strong>
        @endif
      </span>
    @endif
  </div>
  <div class="card-body">
    @if($item->is_manual_override)
    <div class="alert alert-warning d-flex justify-between align-center" style="margin-bottom:20px;flex-wrap:wrap;gap:12px;">
      <div>
        <strong>Perhatian:</strong> Nilai ambang batas ROP & Safety Stock saat ini dikunci secara manual oleh tim procurement/gudang dan mengabaikan hasil prediksi Machine Learning sementara waktu.
        <div style="font-size:12px;margin-top:6px;color:#92400e;">
          Alasan: <em>"{{ $item->override_reason }}"</em> &bull;
          Diubah oleh: <strong>{{ $item->overrideUser?->name ?? 'User' }}</strong> &bull;
          Waktu: {{ $item->override_updated_at?->diffForHumans() ?? '-' }}
        </div>
      </div>
      <form action="{{ route('items.reset-override', $item) }}" method="POST" onsubmit="return confirm('Kembalikan parameter barang ini ke perhitungan Machine Learning otomatis?');">
        @csrf
        <button type="submit" class="btn btn-sm btn-secondary" style="border-color:#d97706;color:#92400e;background:#ffffff;">
          🔄 Kembalikan ke Prediksi ML
        </button>
      </form>
    </div>
    @else
    <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px;">
      Secara standar, nilai Safety Stock (<strong>{{ number_format($item->ml_safety_stock ?? $item->safety_stock, 1) }}</strong>) dan Reorder Point (<strong>{{ number_format($item->ml_rop ?? $item->rop, 1) }}</strong>) dihitung otomatis oleh modul Machine Learning berdasarkan riwayat transaksi. Anda dapat menimpa (override) parameter ini jika terdapat kondisi riil di lapangan seperti fluktuasi mendadak, diskon musiman, atau hambatan rantai pasok supplier.
    </p>
    @endif

    {{-- Form Override --}}
    <form action="{{ route('items.override', $item) }}" method="POST">
      @csrf
      <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:16px;margin-bottom:16px;">
        <div>
          <label class="form-label" for="manual_rop">Nilai Manual ROP ({{ $item->unit }}) <span class="text-danger">*</span></label>
          <input type="number" step="0.01" min="0" name="manual_rop" id="manual_rop" class="form-control" 
                 value="{{ old('manual_rop', $item->manual_rop ?? $item->rop) }}" required placeholder="Contoh: 150">
          <span style="font-size:11px;color:var(--text-muted);">Nilai ROP hasil ML saat ini: <strong>{{ number_format($item->ml_rop ?? $item->rop, 1) }}</strong></span>
        </div>
        <div>
          <label class="form-label" for="manual_safety_stock">Nilai Manual Safety Stock ({{ $item->unit }})</label>
          <input type="number" step="0.01" min="0" name="manual_safety_stock" id="manual_safety_stock" class="form-control" 
                 value="{{ old('manual_safety_stock', $item->manual_safety_stock ?? $item->safety_stock) }}" placeholder="Opsional">
          <span style="font-size:11px;color:var(--text-muted);">Nilai SS hasil ML saat ini: <strong>{{ number_format($item->ml_safety_stock ?? $item->safety_stock, 1) }}</strong></span>
        </div>
        <div style="grid-column: 1 / -1;">
          <label class="form-label" for="override_reason">Alasan Manual Override <span class="text-danger">*</span></label>
          <input type="text" name="override_reason" id="override_reason" class="form-control" 
                 value="{{ old('override_reason', $item->override_reason) }}" required placeholder="Jelaskan alasan bisnis (misal: Antisipasi lonjakan pesanan tender Q4)">
        </div>
      </div>
      <div class="d-flex justify-end gap-2">
        <button type="submit" class="btn btn-primary">
          💾 Simpan Manual Override
        </button>
      </div>
    </form>
  </div>
</div>

{{-- Recent Transactions --}}
<div class="card mt-4">
  <div class="card-header">
    <span class="card-title">Transaksi Terbaru</span>
    @if(auth()->user()->isGudang())
    <a href="{{ route('transactions.create', ['item_id' => $item->id]) }}" class="btn btn-sm btn-primary">+ Transaksi</a>
    @endif
  </div>
  <div class="table-container">
    <table>
      <thead><tr><th>Tanggal</th><th>Tipe</th><th>Qty</th><th>Sebelum</th><th>Sesudah</th><th>Ref</th><th>User</th></tr></thead>
      <tbody>
        @forelse($recentTransactions as $t)
        <tr>
          <td>{{ $t->transaction_date->format('d/m/Y') }}</td>
          <td><span class="badge {{ $t->type === 'in' ? 'badge-success' : ($t->type === 'out' ? 'badge-danger' : 'badge-info') }}">{{ $t->type_label }}</span></td>
          <td><strong>{{ number_format($t->quantity, 0) }}</strong></td>
          <td class="text-muted">{{ number_format($t->stock_before, 0) }}</td>
          <td>{{ number_format($t->stock_after, 0) }}</td>
          <td class="font-mono text-sm text-muted">{{ $t->reference_no ?: '-' }}</td>
          <td>{{ $t->user?->name ?? '-' }}</td>
        </tr>
        @empty
        <tr><td colspan="7" style="text-align:center;padding:20px;color:var(--text-muted);">Belum ada transaksi</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection

@push('scripts')
@if($item->monthlyUsages->count())
<script>
const usages = @json($item->monthlyUsages);
const months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
const labels = usages.map(u => (months[u.month - 1]) + ' ' + u.year);
const data   = usages.map(u => u.total_out);
const ropLine = Array(labels.length).fill({{ $item->planning_usage }});

new Chart(document.getElementById('usageChart'), {
  type: 'bar',
  data: {
    labels,
    datasets: [
      {
        label: 'Pemakaian',
        data,
        backgroundColor: 'rgba(255,255,255,0.2)',
        borderColor: 'rgba(255,255,255,0.6)',
        borderWidth: 1,
        borderRadius: 4,
      },
      {
        label: 'Avg Usage',
        data: ropLine,
        type: 'line',
        borderColor: '#f5a623',
        borderDash: [5,5],
        borderWidth: 2,
        pointRadius: 0,
        fill: false,
      }
    ]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { position: 'top' } },
    scales: {
      x: { grid: { color: '#2a2a2a' }, ticks: { color: '#a0a0a0' } },
      y: { grid: { color: '#1a1a1a' }, ticks: { color: '#a0a0a0' }, beginAtZero: true }
    }
  }
});
</script>
@endif
@endpush
