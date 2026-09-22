@extends('layouts.app')
@section('title', 'Edit Supplier')
@section('content')
<div class="page-header">
  <div class="breadcrumb"><a href="{{ route('suppliers.index') }}">Supplier</a> <span class="breadcrumb-sep">/</span> <span>Edit</span></div>
  <h1 class="page-title">Edit Supplier</h1>
</div>
<div class="card" style="max-width:520px;">
  <div class="card-body">
    <form action="{{ route('suppliers.update', $supplier) }}" method="POST">
      @csrf @method('PUT')
      <div class="form-group"><label class="form-label">Nama Supplier <span class="required">*</span></label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $supplier->name) }}" required maxlength="150">
        @error('name') <div class="form-error">{{ $message }}</div> @enderror</div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Nama Kontak</label>
          <input type="text" name="contact_person" class="form-control" value="{{ old('contact_person', $supplier->contact_person) }}" maxlength="100"></div>
        <div class="form-group"><label class="form-label">Telepon</label>
          <input type="text" name="phone" class="form-control" value="{{ old('phone', $supplier->phone) }}" maxlength="20"></div>
      </div>
      <div class="form-group"><label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" value="{{ old('email', $supplier->email) }}" maxlength="100"></div>
      <div class="form-group"><label class="form-label">Alamat</label>
        <textarea name="address" class="form-control" rows="2">{{ old('address', $supplier->address) }}</textarea></div>
      <div class="d-flex gap-3">
        <a href="{{ route('suppliers.index') }}" class="btn btn-secondary flex-1">Batal</a>
        <button type="submit" class="btn btn-primary flex-1">Simpan</button>
      </div>
    </form>
  </div>
</div>
@endsection
