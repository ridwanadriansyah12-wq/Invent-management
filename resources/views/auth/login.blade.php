@extends('layouts.auth')
@section('title', 'Login')

@section('content')
<h2 class="auth-title">Selamat Datang</h2>
<p class="auth-sub">Masuk ke akun Anda untuk melanjutkan</p>

<form action="{{ route('login') }}" method="POST">
  @csrf

  <div class="form-group">
    <label for="email" class="form-label">Email <span class="required">*</span></label>
    <input type="email" id="email" name="email" class="form-control {{ $errors->has('email') ? 'is-invalid' : '' }}"
      placeholder="nama@email.com" value="{{ old('email') }}" required autofocus>
    @error('email') <div class="form-error">{{ $message }}</div> @enderror
  </div>

  <div class="form-group">
    <label for="password" class="form-label">Password <span class="required">*</span></label>
    <input type="password" id="password" name="password" class="form-control"
      placeholder="••••••••" required>
    @error('password') <div class="form-error">{{ $message }}</div> @enderror
  </div>

  <div class="d-flex align-center justify-between mb-4" style="font-size:13px;">
    <label style="display:flex;align-items:center;gap:6px;cursor:pointer;color:var(--text-secondary)">
      <input type="checkbox" name="remember" style="accent-color:#fff;"> Ingat saya
    </label>
  </div>

  <button type="submit" class="btn btn-primary w-100 btn-lg">Masuk</button>
</form>

<div class="auth-divider" style="margin-top:20px;">atau</div>

<p class="auth-link" style="text-align:center;">
  Akun baru? <a href="{{ route('register') }}">Daftar sebagai Staff Gudang</a>
</p>
@endsection
