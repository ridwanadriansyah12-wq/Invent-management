<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>{{ $title ?? 'Dashboard' }} – Inventory ROP</title>
  <meta name="description" content="Sistem Manajemen Inventaris dengan Dynamic ROP & Safety Stock">
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
  @stack('styles')
</head>
<body>

<div class="sidebar-overlay hidden" id="sidebarOverlay"></div>

<div class="app-wrapper">

  {{-- ── SIDEBAR ─────────────────────────────────────────── --}}
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div class="sidebar-logo"><span>IR</span></div>
      <div class="sidebar-brand-text">
        <div class="sidebar-brand-name">Inventory ROP</div>
        <div class="sidebar-brand-sub">Dynamic ROP & SS</div>
      </div>
    </div>

    {{-- User Profile Card (Matching Screenshot Aesthetic) --}}
    <div class="sidebar-profile">
      <div class="profile-avatar-circle">
        <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#0284c7" stroke-width="1.8">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
        </svg>
      </div>
      <div class="profile-name">{{ auth()->user()->name }}</div>
      <div class="profile-role">{{ auth()->user()->role_label }}</div>
    </div>

    {{-- Main Nav --}}
    <div class="sidebar-section">
      <div class="sidebar-section-label">Menu Utama</div>
      <a href="{{ route('dashboard') }}" class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
        <svg class="nav-icon" fill="none" stroke="#2563eb" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
        Dashboard
      </a>

      {{-- IT Only --}}
      @if(auth()->user()->isIT())
      <a href="{{ route('users.index') }}" class="nav-item {{ request()->routeIs('users.*') ? 'active' : '' }}">
        <svg class="nav-icon" fill="none" stroke="#7c3aed" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
        Kelola Akun
      </a>
      @endif

      {{-- Gudang Only --}}
      @if(auth()->user()->isGudang())
      <div class="sidebar-section-label" style="margin-top:8px">Inventaris</div>
      <a href="{{ route('items.index') }}" class="nav-item {{ request()->routeIs('items.*') ? 'active' : '' }}">
        <svg class="nav-icon" fill="none" stroke="#ea580c" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
        Data Barang
      </a>
      <a href="{{ route('transactions.create') }}" class="nav-item {{ request()->routeIs('transactions.create') ? 'active' : '' }}">
        <svg class="nav-icon" fill="none" stroke="#0284c7" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        Input Transaksi
      </a>
      <a href="{{ route('transactions.index') }}" class="nav-item {{ request()->routeIs('transactions.index') ? 'active' : '' }}">
        <svg class="nav-icon" fill="none" stroke="#0d9488" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        Riwayat Transaksi
      </a>
      <a href="{{ route('suppliers.index') }}" class="nav-item {{ request()->routeIs('suppliers.*') ? 'active' : '' }}">
        <svg class="nav-icon" fill="none" stroke="#d97706" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
        Supplier
      </a>
      <a href="{{ route('categories.index') }}" class="nav-item {{ request()->routeIs('categories.*') ? 'active' : '' }}">
        <svg class="nav-icon" fill="none" stroke="#8b5cf6" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/></svg>
        Kategori
      </a>
      @endif

      {{-- Procurement Only --}}
      @if(auth()->user()->isProcurement())
      <div class="sidebar-section-label" style="margin-top:8px">Procurement</div>
      <a href="{{ route('items.index') }}" class="nav-item {{ request()->routeIs('items.*') ? 'active' : '' }}">
        <svg class="nav-icon" fill="none" stroke="#ea580c" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
        Data Barang
      </a>
      <a href="{{ route('transactions.index') }}" class="nav-item {{ request()->routeIs('transactions.*') ? 'active' : '' }}">
        <svg class="nav-icon" fill="none" stroke="#0d9488" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        Monitor Transaksi
      </a>
      <a href="{{ route('purchase-orders.index') }}" class="nav-item {{ request()->routeIs('purchase-orders.*') ? 'active' : '' }}">
        <svg class="nav-icon" fill="none" stroke="#e11d48" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
        Purchase Order
      </a>
      <a href="{{ route('reports.index') }}" class="nav-item {{ request()->routeIs('reports.*') ? 'active' : '' }}">
        <svg class="nav-icon" fill="none" stroke="#16a34a" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        Laporan & Export
      </a>
      @endif
    </div>

    {{-- Notifications (all roles) --}}
    <div class="sidebar-section">
      <div class="sidebar-section-label">Lainnya</div>
      <a href="{{ route('notifications.index') }}" class="nav-item {{ request()->routeIs('notifications.*') ? 'active' : '' }}">
        <svg class="nav-icon" fill="none" stroke="#0284c7" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
        Notifikasi
        <span class="nav-badge" id="sidebarNotifBadge" style="display:none">0</span>
      </a>
    </div>

    {{-- User info & Logout --}}
    <div class="sidebar-footer">
      <form action="{{ route('logout') }}" method="POST">
        @csrf
        <button type="submit" class="nav-item w-100" style="background:none;border:none;cursor:pointer;color:#dc2626;font-size:13px;font-weight:600;text-align:left;">
          <svg class="nav-icon" fill="none" stroke="#dc2626" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
          Keluar Sistem
        </button>
      </form>
    </div>
  </aside>

  {{-- ── MAIN CONTENT ────────────────────────────────────── --}}
  <div class="main-content">

    {{-- Topbar --}}
    <header class="topbar">
      <button class="menu-toggle" id="menuToggle" aria-label="Toggle menu">
        <span></span><span></span><span></span>
      </button>
      <h2 class="topbar-title">{{ $title ?? 'Dashboard' }}</h2>
      <div class="topbar-actions">
        <a href="{{ route('notifications.index') }}" class="notif-btn" title="Notifikasi">
          <span id="notifDot" class="notif-dot"></span>
          <span id="notifCount" class="notif-count"></span>
          <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
        </a>
        <div class="avatar" title="{{ auth()->user()->name }}">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
      </div>
    </header>

    {{-- Flash Messages --}}
    <div style="padding:0 24px;margin-top:16px;">
      @if(session('success'))
        <div class="alert alert-success">
          <span>✅</span> <span>{{ session('success') }}</span>
          <button class="alert-close">×</button>
        </div>
      @endif
      @if(session('error'))
        <div class="alert alert-error">
          <span>❌</span> <span>{{ session('error') }}</span>
          <button class="alert-close">×</button>
        </div>
      @endif
      @if($errors->any())
        <div class="alert alert-error">
          <span>⚠️</span>
          <ul style="margin:0;padding-left:16px;">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
          </ul>
          <button class="alert-close">×</button>
        </div>
      @endif
    </div>

    {{-- Page Content --}}
    <main class="page-content animate-fade">
      @yield('content')
    </main>

  </div>{{-- /main-content --}}
</div>{{-- /app-wrapper --}}

{{-- Global Delete Confirm Modal --}}
<div class="modal-overlay" id="deleteModal">
  <div class="modal">
    <div class="modal-title" id="deleteTitle">Hapus Data</div>
    <div class="modal-body" id="deleteMsg">Apakah Anda yakin ingin menghapus data ini? Tindakan ini tidak dapat dibatalkan.</div>
    <div class="modal-actions">
      <button class="btn btn-secondary" id="deleteCancel">Batal</button>
      <button class="btn btn-danger" id="deleteConfirm">Hapus</button>
    </div>
  </div>
{{-- Global Toast Container --}}
<div id="toastContainer"></div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="{{ asset('js/app.js') }}"></script>
@stack('scripts')
</body>
</html>
