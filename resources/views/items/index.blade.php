@extends('layouts.app')
@section('title', 'Monitoring Inventaris & Dynamic ROP')

@section('content')
<div class="page-header d-flex align-center justify-between" style="flex-wrap:wrap;gap:12px;">
  <div>
    <h1 class="page-title">Monitoring Inventaris & Dynamic ROP</h1>
    <p class="page-sub">Pantau ambang batas pemesanan ulang secara realtime & intervensi parameter Machine Learning</p>
  </div>
  <div class="d-flex gap-2">
    @if(auth()->user()->isGudang())
    <button type="button" class="btn btn-secondary" onclick="document.getElementById('importModal').style.display='flex'">
      <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
      Import Excel
    </button>
    <a href="{{ route('items.create') }}" class="btn btn-primary">
      <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      Tambah Barang
    </a>
    @endif
  </div>
</div>

{{-- Quick Filter Pills (High-Level Status Metrics) --}}
<div class="quick-filter-pills">
  <a href="{{ route('items.index') }}" 
     class="filter-pill {{ !request()->hasAny(['reorder_only', 'status', 'override_mode']) ? 'active' : '' }}">
    <span>Semua Barang</span>
    <span class="pill-counter">{{ $stats['total'] }}</span>
  </a>

  <a href="{{ route('items.index', array_merge(request()->except(['page']), ['reorder_only' => request('reorder_only') ? null : '1'])) }}" 
     class="filter-pill {{ request('reorder_only') ? 'active-danger' : '' }}">
    <span>🚨 Butuh Reorder (Stok &le; ROP)</span>
    <span class="pill-counter" style="{{ request('reorder_only') ? 'background:rgba(239,68,68,0.3);color:#fff;' : '' }}">{{ $stats['reorder_needed'] }}</span>
  </a>

  <a href="{{ route('items.index', array_merge(request()->except(['page']), ['status' => request('status') === 'critical' ? null : 'critical'])) }}" 
     class="filter-pill {{ request('status') === 'critical' ? 'active-warning' : '' }}">
    <span>⚠️ Stok Kritis (&le; SS)</span>
    <span class="pill-counter">{{ $stats['critical'] }}</span>
  </a>

  <a href="{{ route('items.index', array_merge(request()->except(['page']), ['override_mode' => request('override_mode') === 'manual' ? null : 'manual'])) }}" 
     class="filter-pill {{ request('override_mode') === 'manual' ? 'active-warning' : '' }}">
    <span>👤 Manual Override</span>
    <span class="pill-counter">{{ $stats['overridden'] }}</span>
  </a>
</div>

{{-- Search & Filter Bar --}}
<form method="GET" action="{{ route('items.index') }}" class="filter-bar mb-4" id="filterForm">
  @if(request('reorder_only'))
    <input type="hidden" name="reorder_only" value="1">
  @endif

  <div class="input-group flex-1" style="min-width:240px;max-width:320px;">
    <svg class="input-group-icon" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
    </svg>
    <input type="text" name="search" id="searchInput" class="form-control" 
           placeholder="Cari SKU / nama barang..." value="{{ request('search') }}" autocomplete="off">
  </div>

  <select name="category_id" class="form-control" style="max-width:180px;" onchange="this.form.submit()">
    <option value="">Semua Kategori</option>
    @foreach($categories as $cat)
      <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
    @endforeach
  </select>

  <select name="status" class="form-control" style="max-width:160px;" onchange="this.form.submit()">
    <option value="">Semua Status Stok</option>
    <option value="normal"   {{ request('status') == 'normal'   ? 'selected' : '' }}>Normal (Aman)</option>
    <option value="low"      {{ request('status') == 'low'      ? 'selected' : '' }}>Low Stock (Menipis)</option>
    <option value="critical" {{ request('status') == 'critical' ? 'selected' : '' }}>Kritis (&le; SS)</option>
    <option value="out"      {{ request('status') == 'out'      ? 'selected' : '' }}>Habis (0)</option>
  </select>

  <select name="override_mode" class="form-control" style="max-width:170px;" onchange="this.form.submit()">
    <option value="">Semua Parameter</option>
    <option value="ml"     {{ request('override_mode') == 'ml'     ? 'selected' : '' }}>🤖 Model ML</option>
    <option value="manual" {{ request('override_mode') == 'manual' ? 'selected' : '' }}>👤 Manual Override</option>
  </select>

  @if(request()->hasAny(['search', 'category_id', 'status', 'reorder_only', 'override_mode']))
    <a href="{{ route('items.index') }}" class="btn btn-secondary">Reset Filter</a>
  @endif
</form>

{{-- Inventory Monitor Data Table --}}
<div class="card">
  <div class="table-container">
    <table>
      <thead>
        <tr>
          <th style="width:110px;">Kode SKU</th>
          <th style="min-width:200px;">Nama Barang</th>
          <th style="width:130px;">Kategori</th>
          <th style="width:120px;">Stok Fisik</th>
          <th style="width:130px;">Dynamic ROP</th>
          <th style="width:120px;">Safety Stock</th>
          <th style="width:110px;">Status</th>
          <th style="width:160px;text-align:right;">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse($items as $item)
        @php
          $status = $item->stock_status;
          $isReorderAlert = ($item->rop > 0 && $item->stock_on_hand <= $item->rop);
          $rowClass = $isReorderAlert ? 'row-reorder-alert' : '';
        @endphp
        <tr id="item-row-{{ $item->id }}" class="{{ $rowClass }}">
          {{-- SKU --}}
          <td>
            <span class="item-code">{{ $item->code }}</span>
          </td>

          {{-- Name & Unit --}}
          <td>
            <a href="{{ route('items.show', $item) }}" style="font-weight:600;color:var(--text-primary);display:block;">
              {{ $item->name }}
            </a>
            <div class="text-sm text-muted">Satuan: {{ $item->unit }}</div>
          </td>

          {{-- Category --}}
          <td>
            <span class="badge badge-white">{{ $item->category?->name ?? '-' }}</span>
          </td>

          {{-- Stock on Hand --}}
          <td>
            <div class="font-bold {{ $item->stock_on_hand <= 0 ? 'text-danger' : ($isReorderAlert ? 'text-warning' : '') }}" 
                 style="font-size:14px;">
              <span class="row-stock-val">{{ number_format($item->stock_on_hand, 0) }}</span> 
              <span style="font-size:11px;font-weight:normal;color:var(--text-muted);">{{ $item->unit }}</span>
            </div>
            @if($item->max_stock > 0)
              @php $pct = min(100, max(0, ($item->stock_on_hand / $item->max_stock) * 100)); @endphp
              <div class="progress" style="margin-top:4px;width:70px;height:4px;" data-stock-progress="{{ $pct }}">
                <div class="progress-bar" style="width:{{ $pct }}%"></div>
              </div>
            @endif
          </td>

          {{-- Dynamic ROP --}}
          <td>
            <div class="d-flex align-center gap-1">
              <strong class="row-rop-val" style="font-size:13px;">{{ number_format($item->rop, 1) }}</strong>
              <span class="text-muted" style="font-size:11px;">{{ $item->unit }}</span>
            </div>
            <div class="row-param-badge" style="margin-top:2px;">
              @if($item->is_manual_override)
                <span class="badge-param-override" title="Di-override secara manual">👤 Manual</span>
              @else
                <span class="badge-param-ml" title="Prediksi Machine Learning">🤖 ML</span>
              @endif
            </div>
          </td>

          {{-- Safety Stock --}}
          <td>
            <div class="row-ss-val" style="font-size:13px;font-weight:500;">
              {{ number_format($item->safety_stock, 1) }} <span class="text-muted" style="font-size:11px;">{{ $item->unit }}</span>
            </div>
          </td>

          {{-- Stock Status --}}
          <td>
            <div class="row-status-badge">
              @if($isReorderAlert && $status !== 'out_of_stock')
                <span class="badge badge-warning" style="display:inline-flex;align-items:center;gap:3px;">
                  ⚠️ Butuh Order
                </span>
              @elseif($status === 'out_of_stock')
                <span class="badge badge-danger">⛔ Habis</span>
              @elseif($status === 'critical')
                <span class="badge badge-danger">Kritis</span>
              @else
                <span class="badge badge-success">✓ Aman</span>
              @endif
            </div>
          </td>

          {{-- Actions --}}
          <td style="text-align:right;">
            <div class="d-flex justify-end gap-1">
              {{-- Quick Manual Override Button --}}
              <button type="button" class="btn btn-sm btn-secondary btn-quick-override"
                      title="Quick Manual Override ROP & Safety Stock"
                      data-id="{{ $item->id }}"
                      data-code="{{ $item->code }}"
                      data-name="{{ $item->name }}"
                      data-unit="{{ $item->unit }}"
                      data-stock="{{ $item->stock_on_hand }}"
                      data-rop="{{ $item->rop }}"
                      data-ss="{{ $item->safety_stock }}"
                      data-ml-rop="{{ $item->ml_rop ?? $item->rop }}"
                      data-ml-ss="{{ $item->ml_safety_stock ?? $item->safety_stock }}"
                      data-is-override="{{ $item->is_manual_override ? '1' : '0' }}"
                      data-reason="{{ $item->override_reason ?? '' }}"
                      data-url-override="{{ route('items.override', $item) }}"
                      data-url-reset="{{ route('items.reset-override', $item) }}">
                <span style="font-size:13px;">🎛️</span> Override
              </button>

              <a href="{{ route('items.show', $item) }}" class="btn btn-sm btn-secondary" title="Lihat Detail Barang">
                Detail
              </a>

              @if(auth()->user()->isGudang())
              <a href="{{ route('items.edit', $item) }}" class="btn btn-sm btn-secondary" title="Edit Barang">
                Edit
              </a>
              @endif
            </div>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="8" style="text-align:center;padding:48px 16px;">
            <div class="empty-state">
              <div class="empty-icon" style="font-size:32px;margin-bottom:8px;">📦</div>
              <div class="empty-title" style="font-size:15px;font-weight:600;">Tidak ada barang yang cocok</div>
              <div class="empty-desc" style="color:var(--text-muted);font-size:13px;margin-top:4px;">
                Coba sesuaikan filter atau kata kunci pencarian Anda
              </div>
              <a href="{{ route('items.index') }}" class="btn btn-secondary btn-sm" style="margin-top:12px;">Reset Semua Filter</a>
            </div>
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if($items->hasPages())
  <div style="padding:12px 16px;border-top:1px solid var(--border);">
    {{ $items->links('vendor.pagination.simple-default') }}
  </div>
  @endif
</div>

{{-- ── QUICK MANUAL OVERRIDE MODAL (HUMAN-IN-THE-LOOP) ─────────────────────── --}}
<div id="quickOverrideModal" class="modal-overlay" style="display:none;">
  <div class="modal" style="max-width:520px;width:95%;">
    <div class="modal-header d-flex justify-between align-center" style="padding-bottom:14px;border-bottom:1px solid var(--border);">
      <div>
        <div style="display:flex;align-items:center;gap:8px;">
          <span style="font-size:18px;">🎛️</span>
          <h3 class="modal-title" style="margin:0;font-size:16px;">Quick Manual Override</h3>
        </div>
        <p style="font-size:12px;color:var(--text-muted);margin:2px 0 0 26px;" id="modalItemSubtitle">
          SKU: - · Nama Barang
        </p>
      </div>
      <button type="button" id="closeOverrideModal" style="background:none;border:none;font-size:20px;color:var(--text-muted);cursor:pointer;">&times;</button>
    </div>

    <form id="quickOverrideForm" method="POST">
      @csrf
      <div class="modal-body" style="padding:16px 0;">
        
        {{-- Switch: Toggle Manual Override --}}
        <div style="background:var(--bg-surface);padding:12px 14px;border-radius:8px;border:1px solid var(--border);margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;">
          <div>
            <div style="font-weight:600;font-size:13px;color:var(--text-primary);">Aktifkan Override Manual</div>
            <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">
              Jika aktif, nilai di bawah akan menggantikan prediksi ML
            </div>
          </div>
          <label class="switch-container">
            <input type="checkbox" id="overrideToggle" class="switch-input">
            <span class="switch-slider"></span>
          </label>
        </div>

        {{-- Benchmark ML Reference Banner --}}
        <div style="background:rgba(59,130,246,0.08);border:1px solid rgba(59,130,246,0.25);border-radius:6px;padding:10px 12px;margin-bottom:16px;display:flex;justify-content:space-between;font-size:12px;">
          <div>
            <span style="color:var(--text-muted);">Acuan Prediksi ML:</span>
          </div>
          <div style="display:flex;gap:16px;">
            <span>ROP ML: <strong id="modalMlRop" style="color:#60a5fa;">-</strong></span>
            <span>SS ML: <strong id="modalMlSs" style="color:#60a5fa;">-</strong></span>
          </div>
        </div>

        {{-- Input Fields --}}
        <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
          <div>
            <label class="form-label" for="inputManualRop" style="font-size:12px;">
              Nilai ROP Baru <span class="text-danger">*</span>
            </label>
            <input type="number" step="0.01" min="0" id="inputManualRop" name="manual_rop" class="form-control" required>
            <div class="text-sm text-muted" style="font-size:10px;margin-top:2px;">Ambang pemesanan kembali</div>
          </div>

          <div>
            <label class="form-label" for="inputManualSs" style="font-size:12px;">
              Safety Stock Baru
            </label>
            <input type="number" step="0.01" min="0" id="inputManualSs" name="manual_safety_stock" class="form-control">
            <div class="text-sm text-muted" style="font-size:10px;margin-top:2px;">Stok penyangga cadangan</div>
          </div>
        </div>

        {{-- Reason / Audit Note --}}
        <div style="margin-bottom:6px;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
            <label class="form-label" for="inputReason" style="font-size:12px;margin:0;">
              Alasan Override <span class="text-danger">*</span>
            </label>
            <span id="reasonCharCount" style="font-size:10px;color:var(--text-muted);">0 / 100</span>
          </div>
          <input type="text" id="inputReason" name="override_reason" maxlength="100" class="form-control" 
                 placeholder="Contoh: Lonjakan permintaan tender Q4 / supplier delay">
        </div>
      </div>

      <div class="modal-actions d-flex justify-end gap-2" style="border-top:1px solid var(--border);padding-top:14px;">
        <button type="button" class="btn btn-secondary" id="cancelOverrideBtn">Batal</button>
        <button type="submit" class="btn btn-primary" id="saveOverrideBtn">
          <span class="btn-text">💾 Simpan Perubahan</span>
        </button>
      </div>
    </form>
  </div>
</div>

@if(auth()->user()->isGudang())
{{-- Import Excel Modal --}}
<div id="importModal" class="modal-backdrop" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);z-index:999;align-items:center;justify-content:center;">
  <div class="card" style="width:100%;max-width:500px;margin:20px;">
    <div class="card-header d-flex justify-between align-center">
      <span class="card-title">Import Data Barang</span>
      <button type="button" onclick="document.getElementById('importModal').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer;color:var(--text-muted);">&times;</button>
    </div>
    <div class="card-body">
      <p style="font-size:13px;color:var(--text-secondary);margin-bottom:16px;">
        Unggah file Excel (.xlsx / .csv) dari sistem SAP/ERP Anda untuk sinkronisasi inventaris.
      </p>
      <form action="{{ route('items.import') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="form-group">
          <label class="form-label">Pilih File Excel/CSV <span class="text-danger">*</span></label>
          <input type="file" name="file" class="form-control" accept=".xlsx,.xls,.csv" required>
        </div>
        <div class="d-flex justify-end gap-2 mt-4">
          <button type="button" class="btn btn-secondary" onclick="document.getElementById('importModal').style.display='none'">Batal</button>
          <button type="submit" class="btn btn-primary">Mulai Import</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  // ── 1. Search Debounce Optimization (350ms) ────────────────────────────────
  const searchInput = document.getElementById('searchInput');
  const filterForm  = document.getElementById('filterForm');
  let searchTimer   = null;

  if (searchInput && filterForm) {
    searchInput.addEventListener('input', () => {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(() => {
        filterForm.submit();
      }, 350);
    });
  }

  // ── 2. Quick Override Modal Logic ──────────────────────────────────────────
  const modal           = document.getElementById('quickOverrideModal');
  const modalSubtitle   = document.getElementById('modalItemSubtitle');
  const modalMlRop      = document.getElementById('modalMlRop');
  const modalMlSs       = document.getElementById('modalMlSs');
  const form            = document.getElementById('quickOverrideForm');
  const toggle          = document.getElementById('overrideToggle');
  const inputRop        = document.getElementById('inputManualRop');
  const inputSs         = document.getElementById('inputManualSs');
  const inputReason     = document.getElementById('inputReason');
  const charCounter     = document.getElementById('reasonCharCount');
  const closeBtn        = document.getElementById('closeOverrideModal');
  const cancelBtn       = document.getElementById('cancelOverrideBtn');
  const saveBtn         = document.getElementById('saveOverrideBtn');

  let activeItemData    = null;

  function closeModal() {
    if (modal) {
      modal.classList.remove('open');
      modal.style.display = 'none';
    }
  }

  closeBtn?.addEventListener('click', closeModal);
  cancelBtn?.addEventListener('click', closeModal);
  modal?.addEventListener('click', (e) => {
    if (e.target === modal) closeModal();
  });

  // Live Character Counter
  inputReason?.addEventListener('input', function() {
    charCounter.textContent = `${this.value.length} / 100`;
  });

  // Switch Toggle Behavior: enable/disable inputs
  toggle?.addEventListener('change', function() {
    const isChecked = this.checked;
    inputRop.disabled    = !isChecked;
    inputSs.disabled     = !isChecked;
    inputReason.disabled = !isChecked;

    if (!isChecked) {
      inputRop.value    = activeItemData ? activeItemData.mlRop : '';
      inputSs.value     = activeItemData ? activeItemData.mlSs : '';
      inputReason.value = '';
      charCounter.textContent = '0 / 100';
    }
  });

  // Open Modal on Button Click
  document.querySelectorAll('.btn-quick-override').forEach(btn => {
    btn.addEventListener('click', () => {
      const d = btn.dataset;
      activeItemData = {
        id:         d.id,
        code:       d.code,
        name:       d.name,
        unit:       d.unit,
        stock:      parseFloat(d.stock) || 0,
        rop:        parseFloat(d.rop) || 0,
        ss:         parseFloat(d.ss) || 0,
        mlRop:      parseFloat(d.mlRop) || 0,
        mlSs:       parseFloat(d.mlSs) || 0,
        isOverride: d.isOverride === '1',
        reason:     d.reason || '',
        urlOverride:d.urlOverride,
        urlReset:   d.urlReset,
      };

      // Populate Modal Fields
      modalSubtitle.textContent = `[${activeItemData.code}] ${activeItemData.name} · Satuan: ${activeItemData.unit}`;
      modalMlRop.textContent    = `${activeItemData.mlRop} ${activeItemData.unit}`;
      modalMlSs.textContent     = `${activeItemData.mlSs} ${activeItemData.unit}`;

      toggle.checked = activeItemData.isOverride;
      inputRop.value = activeItemData.rop;
      inputSs.value  = activeItemData.ss;
      inputReason.value = activeItemData.reason;
      charCounter.textContent = `${activeItemData.reason.length} / 100`;

      // Enable or disable based on toggle initial state
      inputRop.disabled    = !toggle.checked;
      inputSs.disabled     = !toggle.checked;
      inputReason.disabled = !toggle.checked;

      // Show Modal
      modal.style.display = 'flex';
      modal.classList.add('open');
      if (toggle.checked) {
        inputRop.focus();
      }
    });
  });

  // ── 3. Form Submit Handler via AJAX (Optimistic UI & Toast) ─────────────────
  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!activeItemData) return;

    const isEnablingOverride = toggle.checked;
    const manualRop = parseFloat(inputRop.value);
    const manualSs  = inputSs.value !== '' ? parseFloat(inputSs.value) : null;
    const reason    = inputReason.value.trim();

    // Client-side Validation
    if (isEnablingOverride) {
      if (isNaN(manualRop) || manualRop < 0) {
        window.showToast('Nilai ROP harus berupa angka positif (≥ 0).', 'error');
        inputRop.focus();
        return;
      }
      if (manualSs !== null && (isNaN(manualSs) || manualSs < 0)) {
        window.showToast('Nilai Safety Stock harus berupa angka positif (≥ 0).', 'error');
        inputSs.focus();
        return;
      }
      if (!reason) {
        window.showToast('Alasan manual override wajib diisi untuk catatan audit.', 'warning');
        inputReason.focus();
        return;
      }
    }

    // Indicate loading state
    saveBtn.disabled = true;
    const originalBtnText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<span>Menyimpan...</span>';

    try {
      const targetUrl = isEnablingOverride ? activeItemData.urlOverride : activeItemData.urlReset;
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

      const formData = new FormData();
      if (isEnablingOverride) {
        formData.append('manual_rop', manualRop);
        if (manualSs !== null) formData.append('manual_safety_stock', manualSs);
        formData.append('override_reason', reason);
      }

      const res = await fetch(targetUrl, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrfToken,
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
      });

      const result = await res.json();

      if (res.ok && result.success) {
        // Optimistic UI Update on the target row
        const row = document.getElementById(`item-row-${activeItemData.id}`);
        if (row && result.item) {
          const item = result.item;
          
          // Update ROP & SS text
          const ropElem = row.querySelector('.row-rop-val');
          const ssElem  = row.querySelector('.row-ss-val');
          if (ropElem) ropElem.textContent = Number(item.rop).toFixed(1);
          if (ssElem)  ssElem.innerHTML = `${Number(item.safety_stock).toFixed(1)} <span class="text-muted" style="font-size:11px;">${item.unit}</span>`;

          // Update Badge Source (ML vs Override)
          const badgeContainer = row.querySelector('.row-param-badge');
          if (badgeContainer) {
            badgeContainer.innerHTML = item.is_manual_override
              ? `<span class="badge-param-override" title="Di-override secara manual">👤 Manual</span>`
              : `<span class="badge-param-ml" title="Prediksi Machine Learning">🤖 ML</span>`;
          }

          // Update Status Badge & Row Alert Highlight
          const statusContainer = row.querySelector('.row-status-badge');
          if (item.is_reorder_needed && item.stock_status !== 'out_of_stock') {
            row.classList.add('row-reorder-alert');
            if (statusContainer) {
              statusContainer.innerHTML = `<span class="badge badge-warning">⚠️ Butuh Order</span>`;
            }
          } else {
            row.classList.remove('row-reorder-alert');
            if (statusContainer) {
              if (item.stock_status === 'out_of_stock') {
                statusContainer.innerHTML = `<span class="badge badge-danger">⛔ Habis</span>`;
              } else if (item.stock_status === 'critical') {
                statusContainer.innerHTML = `<span class="badge badge-danger">Kritis</span>`;
              } else {
                statusContainer.innerHTML = `<span class="badge badge-success">✓ Aman</span>`;
              }
            }
          }

          // Update button data attributes for next click
          const overrideBtn = row.querySelector('.btn-quick-override');
          if (overrideBtn) {
            overrideBtn.dataset.rop        = item.rop;
            overrideBtn.dataset.ss         = item.safety_stock;
            overrideBtn.dataset.isOverride = item.is_manual_override ? '1' : '0';
            overrideBtn.dataset.reason     = item.override_reason || '';
          }
        }

        closeModal();
        window.showToast(result.message || 'Parameter berhasil diperbarui.', 'success');
      } else {
        window.showToast(result.message || 'Gagal menyimpan parameter.', 'error');
      }
    } catch (err) {
      window.showToast('Terjadi kesalahan jaringan atau server saat menyimpan.', 'error');
    } finally {
      saveBtn.disabled = false;
      saveBtn.innerHTML = originalBtnText;
    }
  });
});
</script>
@endpush
