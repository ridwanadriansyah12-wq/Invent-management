@extends('layouts.auth')
@section('title', 'Daftar Akun Gudang')

@section('content')
<h2 class="auth-title">Daftar Akun</h2>
<p class="auth-sub">Registrasi untuk Staff Gudang</p>

<div class="badge badge-white mb-4" style="font-size:12px;">
  🏭 Akun yang dibuat akan memiliki role: <strong>Gudang</strong>
</div>

<form action="{{ route('register') }}" method="POST">
  @csrf

  <div class="form-group">
    <label for="name" class="form-label">Nama Lengkap <span class="required">*</span></label>
    <input type="text" id="name" name="name" class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}"
      placeholder="Nama lengkap" value="{{ old('name') }}" required autofocus>
    @error('name') <div class="form-error">{{ $message }}</div> @enderror
  </div>

  <div class="form-group">
    <label for="email" class="form-label">Email <span class="required">*</span></label>
    <input type="email" id="email" name="email" class="form-control {{ $errors->has('email') ? 'is-invalid' : '' }}"
      placeholder="nama@email.com" value="{{ old('email') }}" required>
    @error('email') <div class="form-error">{{ $message }}</div> @enderror
  </div>

  <div class="form-group">
    <label for="password" class="form-label">Password <span class="required">*</span></label>
    <input type="password" id="password" name="password" class="form-control"
      placeholder="Minimal 8 karakter" required>
    @error('password') <div class="form-error">{{ $message }}</div> @enderror
  </div>

  <div class="form-group">
    <label for="password_confirmation" class="form-label">Konfirmasi Password <span class="required">*</span></label>
    <input type="password" id="password_confirmation" name="password_confirmation" class="form-control"
      placeholder="Ulangi password" required>
  </div>

  <button type="submit" class="btn btn-primary w-100 btn-lg">Buat Akun</button>
</form>

<p class="auth-link" style="text-align:center;margin-top:16px;">
  Sudah punya akun? <a href="{{ route('login') }}">Masuk</a>
</p>
@endsection
