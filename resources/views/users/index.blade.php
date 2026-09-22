@extends('layouts.app')
@section('title', 'Kelola Akun')

@section('content')
<div class="page-header d-flex align-center justify-between">
  <div>
    <h1 class="page-title">Kelola Akun User</h1>
    <p class="page-sub">Manajemen akun semua pengguna sistem</p>
  </div>
  <a href="{{ route('users.create') }}" class="btn btn-primary">+ Tambah User</a>
</div>

<form method="GET" class="filter-bar mb-4">
  <div class="input-group flex-1" style="max-width:280px;">
    <svg class="input-group-icon" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
    <input type="text" name="search" class="form-control" placeholder="Cari nama/email..." value="{{ request('search') }}">
  </div>
  <select name="role" class="form-control" style="max-width:180px;">
    <option value="">Semua Role</option>
    <option value="it"          {{ request('role') === 'it'          ? 'selected' : '' }}>IT Admin</option>
    <option value="procurement" {{ request('role') === 'procurement' ? 'selected' : '' }}>Procurement</option>
    <option value="gudang"      {{ request('role') === 'gudang'      ? 'selected' : '' }}>Gudang</option>
  </select>
  <button type="submit" class="btn btn-secondary">Filter</button>
  @if(request()->hasAny(['search','role']))
    <a href="{{ route('users.index') }}" class="btn btn-secondary">Reset</a>
  @endif
</form>

<div class="card">
  <div class="table-container">
    <table>
      <thead>
        <tr><th>Nama</th><th>Email</th><th>Role</th><th>Status</th><th>Dibuat</th><th>Aksi</th></tr>
      </thead>
      <tbody>
        @forelse($users as $user)
        <tr>
          <td>
            <div class="d-flex align-center gap-2">
              <div class="avatar" style="width:28px;height:28px;font-size:11px;">{{ strtoupper(substr($user->name, 0, 1)) }}</div>
              <span class="font-bold">{{ $user->name }}</span>
              @if($user->id === auth()->id()) <span class="badge badge-info text-sm">Anda</span> @endif
            </div>
          </td>
          <td class="text-muted text-sm">{{ $user->email }}</td>
          <td>
            <span class="badge {{ match($user->role) { 'it' => 'badge-white', 'procurement' => 'badge-info', 'gudang' => 'badge-secondary' } }}">
              {{ $user->role_label }}
            </span>
          </td>
          <td>
            <span class="badge {{ $user->is_active ? 'badge-success' : 'badge-danger' }}">
              {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
            </span>
          </td>
          <td class="text-muted text-sm">{{ $user->created_at->format('d/m/Y') }}</td>
          <td>
            <div class="d-flex gap-1">
              <a href="{{ route('users.edit', $user) }}" class="btn btn-sm btn-secondary">Edit</a>
              @if($user->id !== auth()->id())
              <form action="{{ route('users.toggle-active', $user) }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-sm {{ $user->is_active ? 'btn-warning' : 'btn-success' }}">
                  {{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                </button>
              </form>
              @endif
            </div>
          </td>
        </tr>
        @empty
        <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--text-muted);">Tidak ada user</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if($users->hasPages())
  <div style="padding:0 16px;">{{ $users->links('vendor.pagination.simple-default') }}</div>
  @endif
</div>
@endsection
