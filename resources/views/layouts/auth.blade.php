<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ $title ?? 'Login' }} – Inventory ROP</title>
  <meta name="description" content="Login ke Sistem Manajemen Inventaris ROP">
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<div class="auth-bg">
  <div class="auth-card animate-fade">
    <div class="auth-logo">
      <div class="auth-logo-icon">IR</div>
      <div class="auth-logo-text">
        <h1>Inventory ROP</h1>
        <p>Dynamic ROP & Safety Stock System</p>
      </div>
    </div>
    @if(session('error'))
      <div class="alert alert-error" style="margin-bottom:20px;">
        <span>❌</span> <span>{{ session('error') }}</span>
      </div>
    @endif
    @yield('content')
  </div>
</div>
</body>
</html>
