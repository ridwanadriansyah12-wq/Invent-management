@extends('layouts.app')
@section('title', 'Tambah User')

@section('content')
<div class="page-header">
  <div class="breadcrumb"><a href="{{ route('users.index') }}">Kelola Akun</a> <span class="breadcrumb-sep">/</span> <span>Tambah</span></div>
  <h1 class="page-title">Tambah User Baru</h1>
</div>

<div class="card" style="max-width:520px;">
  <div class="card-header"><span class="card-title">Informasi Akun</span></div>
  <div class="card-body">
    <form action="{{ route('users.store') }}" method="POST">
      @csrf
      <div class="form-group">
        <label class="form-label">Nama Lengkap <span class="required">*</span></label>
        <input type="text" name="name" class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}"
          value="{{ old('name') }}" placeholder="Nama lengkap" required>
        @error('name') <div class="form-error">{{ $message }}</div> @enderror
      </div>
      <div class="form-group">
        <label class="form-label">Email <span class="required">*</span></label>
        <input type="email" name="email" class="form-control {{ $errors->has('email') ? 'is-invalid' : '' }}"
          value="{{ old('email') }}" placeholder="nama@email.com" required>
        @error('email') <div class="form-error">{{ $message }}</div> @enderror
      </div>
      <div class="form-group">
        <label class="form-label">Role <span class="required">*</span></label>
        <select name="role" class="form-control" required>
          <option value="it"          {{ old('role') === 'it'          ? 'selected' : '' }}>👑 IT Admin</option>
          <option value="procurement" {{ old('role') === 'procurement' ? 'selected' : '' }}>📊 Procurement</option>
          <option value="gudang"      {{ old('role') === 'gudang'      ? 'selected' : '' }}>🏭 Gudang</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Password <span class="required">*</span></label>
        <input type="password" name="password" class="form-control" placeholder="Min. 8 karakter" required>
        @error('password') <div class="form-error">{{ $message }}</div> @enderror
      </div>
      <div class="form-group">
        <label class="form-label">Konfirmasi Password <span class="required">*</span></label>
        <input type="password" name="password_confirmation" class="form-control" placeholder="Ulangi password" required>
      </div>
      <div class="d-flex gap-3 mt-4">
        <a href="{{ route('users.index') }}" class="btn btn-secondary flex-1">Batal</a>
        <button type="submit" class="btn btn-primary flex-1">Buat Akun</button>
      </div>
    </form>
  </div>
</div>
@endsection
