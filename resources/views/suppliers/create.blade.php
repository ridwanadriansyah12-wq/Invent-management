@extends('layouts.app')
@section('title', 'Tambah Supplier')
@section('content')
<div class="page-header">
  <div class="breadcrumb"><a href="{{ route('suppliers.index') }}">Supplier</a> <span class="breadcrumb-sep">/</span> <span>Tambah</span></div>
  <h1 class="page-title">Tambah Supplier</h1>
</div>
<div class="card" style="max-width:520px;">
  <div class="card-body">
    <form action="{{ route('suppliers.store') }}" method="POST">
      @csrf
      <div class="form-group"><label class="form-label">Nama Supplier <span class="required">*</span></label>
        <input type="text" name="name" class="form-control" value="{{ old('name') }}" required maxlength="150">
        @error('name') <div class="form-error">{{ $message }}</div> @enderror</div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Nama Kontak</label>
          <input type="text" name="contact_person" class="form-control" value="{{ old('contact_person') }}" maxlength="100"></div>
        <div class="form-group"><label class="form-label">Telepon</label>
          <input type="text" name="phone" class="form-control" value="{{ old('phone') }}" maxlength="20"></div>
      </div>
      <div class="form-group"><label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" value="{{ old('email') }}" maxlength="100"></div>
      <div class="form-group"><label class="form-label">Alamat</label>
        <textarea name="address" class="form-control" rows="2">{{ old('address') }}</textarea></div>
      <div class="d-flex gap-3">
        <a href="{{ route('suppliers.index') }}" class="btn btn-secondary flex-1">Batal</a>
        <button type="submit" class="btn btn-primary flex-1">Simpan</button>
      </div>
    </form>
  </div>
</div>
@endsection
