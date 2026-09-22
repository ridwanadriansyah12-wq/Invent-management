@extends('layouts.app')
@section('title', 'Dashboard – IT Admin')

@section('content')
<div class="page-header">
  <h1 class="page-title">Dashboard</h1>
  <p class="page-sub">Selamat datang, {{ auth()->user()->name }} · IT Admin</p>
</div>

<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-icon gray">👥</div>
    <div class="stat-info">
      <div class="stat-value">{{ $totalUsers }}</div>
      <div class="stat-label">Total User</div>
      <div class="stat-delta">{{ $activeUsers }} aktif</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon gray">📦</div>
    <div class="stat-info">
      <div class="stat-value">{{ $totalItems }}</div>
      <div class="stat-label">Total Barang</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon danger">⛔</div>
    <div class="stat-info">
      <div class="stat-value">{{ $outOfStock }}</div>
      <div class="stat-label">Stok Habis</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon warning">⚠️</div>
    <div class="stat-info">
      <div class="stat-value">{{ $lowStock }}</div>
      <div class="stat-label">Stok Rendah</div>
    </div>
  </div>
</div>

<div class="d-flex gap-4" style="flex-wrap:wrap;">
  {{-- User by Role --}}
  <div class="card flex-1" style="min-width:280px;">
    <div class="card-header">
      <span class="card-title">Distribusi User per Role</span>
      <a href="{{ route('users.index') }}" class="btn btn-sm btn-secondary">Kelola Akun</a>
    </div>
    <div class="card-body">
      @php $roles = ['it' => ['IT Admin','👑'], 'procurement' => ['Procurement','📊'], 'gudang' => ['Gudang','🏭']]; @endphp
      @foreach($roles as $key => [$label, $icon])
      <div class="d-flex align-center justify-between mb-3">
        <div class="d-flex align-center gap-2">
          <span style="font-size:18px;">{{ $icon }}</span>
          <span class="font-bold" style="font-size:13px;">{{ $label }}</span>
        </div>
        <span class="badge badge-white">{{ $usersByRole[$key] ?? 0 }} user</span>
      </div>
      @endforeach
    </div>
  </div>

  {{-- Quick Actions --}}
  <div class="card flex-1" style="min-width:280px;">
    <div class="card-header"><span class="card-title">Aksi Cepat</span></div>
    <div class="card-body">
      <a href="{{ route('users.create') }}" class="btn btn-primary w-100 mb-2">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Tambah User Baru
      </a>
      <a href="{{ route('users.index') }}?role=gudang" class="btn btn-secondary w-100 mb-2">🏭 Lihat User Gudang</a>
      <a href="{{ route('users.index') }}?role=procurement" class="btn btn-secondary w-100 mb-2">📊 Lihat Procurement</a>
      <a href="{{ route('notifications.index') }}" class="btn btn-secondary w-100">
        🔔 Notifikasi
        @if($unreadNotifs > 0) <span class="badge badge-danger ml-auto">{{ $unreadNotifs }}</span> @endif
      </a>
    </div>
  </div>
</div>
@endsection
