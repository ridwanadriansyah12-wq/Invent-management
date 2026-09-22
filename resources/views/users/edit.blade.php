@extends('layouts.app')
@section('title', 'Edit User')

@section('content')
<div class="page-header">
  <div class="breadcrumb"><a href="{{ route('users.index') }}">Kelola Akun</a> <span class="breadcrumb-sep">/</span> <span>Edit</span></div>
  <h1 class="page-title">Edit Akun: {{ $user->name }}</h1>
</div>

<div class="card" style="max-width:520px;">
  <div class="card-header"><span class="card-title">Informasi Akun</span></div>
  <div class="card-body">
    <form action="{{ route('users.update', $user) }}" method="POST">
      @csrf @method('PUT')
      <div class="form-group">
        <label class="form-label">Nama Lengkap <span class="required">*</span></label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}" required>
        @error('name') <div class="form-error">{{ $message }}</div> @enderror
      </div>
      <div class="form-group">
        <label class="form-label">Email <span class="required">*</span></label>
        <input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required>
        @error('email') <div class="form-error">{{ $message }}</div> @enderror
      </div>
      <div class="form-group">
        <label class="form-label">Role <span class="required">*</span></label>
        <select name="role" class="form-control" required>
          <option value="it"          {{ old('role', $user->role) === 'it'          ? 'selected' : '' }}>👑 IT Admin</option>
          <option value="procurement" {{ old('role', $user->role) === 'procurement' ? 'selected' : '' }}>📊 Procurement</option>
          <option value="gudang"      {{ old('role', $user->role) === 'gudang'      ? 'selected' : '' }}>🏭 Gudang</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Status Akun</label>
        <div style="display:flex;align-items:center;gap:10px;margin-top:4px;">
          <input type="checkbox" name="is_active" id="is_active" value="1"
            {{ old('is_active', $user->is_active) ? 'checked' : '' }}
            {{ $user->id === auth()->id() ? 'disabled' : '' }}
            style="width:16px;height:16px;accent-color:#fff;">
          <label for="is_active" style="font-size:13px;color:var(--text-secondary);cursor:pointer;">Akun Aktif</label>
        </div>
        @if($user->id === auth()->id())
          <div class="form-hint">Anda tidak bisa menonaktifkan akun sendiri.</div>
        @endif
      </div>

      <hr class="divider">

      <div class="form-group">
        <label class="form-label">Password Baru <span style="color:var(--text-muted);font-weight:400;">(kosongkan jika tidak diubah)</span></label>
        <input type="password" name="password" class="form-control" placeholder="Min. 8 karakter">
        @error('password') <div class="form-error">{{ $message }}</div> @enderror
      </div>
      <div class="form-group">
        <label class="form-label">Konfirmasi Password</label>
        <input type="password" name="password_confirmation" class="form-control" placeholder="Ulangi password baru">
      </div>

      <div class="d-flex gap-3 mt-4">
        <a href="{{ route('users.index') }}" class="btn btn-secondary flex-1">Batal</a>
        <button type="submit" class="btn btn-primary flex-1">Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>
@endsection
