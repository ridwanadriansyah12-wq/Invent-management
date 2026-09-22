@extends('layouts.app')
@section('title', 'Supplier')

@section('content')
<div class="page-header d-flex align-center justify-between">
  <div><h1 class="page-title">Data Supplier</h1><p class="page-sub">Daftar supplier barang</p></div>
  <a href="{{ route('suppliers.create') }}" class="btn btn-primary">+ Tambah Supplier</a>
</div>

<div class="card">
  <div class="table-container">
    <table>
      <thead><tr><th>Nama</th><th>Contact</th><th>Telepon</th><th>Email</th><th>Barang</th><th>Aksi</th></tr></thead>
      <tbody>
        @forelse($suppliers as $s)
        <tr>
          <td><span class="font-bold">{{ $s->name }}</span></td>
          <td class="text-muted">{{ $s->contact_person ?? '-' }}</td>
          <td class="font-mono text-sm">{{ $s->phone ?? '-' }}</td>
          <td class="text-muted text-sm">{{ $s->email ?? '-' }}</td>
          <td><span class="badge badge-white">{{ $s->items_count }} barang</span></td>
          <td>
            <div class="d-flex gap-1">
              <a href="{{ route('suppliers.edit', $s) }}" class="btn btn-sm btn-secondary">Edit</a>
              <form action="{{ route('suppliers.destroy', $s) }}" method="POST">
                @csrf @method('DELETE')
                <button type="button" class="btn btn-sm btn-danger" data-delete
                  data-delete-title="Hapus Supplier"
                  data-delete-msg="Hapus supplier {{ $s->name }}? Pastikan tidak ada barang terhubung.">Hapus</button>
              </form>
            </div>
          </td>
        </tr>
        @empty
        <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--text-muted);">Belum ada supplier</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if($suppliers->hasPages())
  <div style="padding:0 16px;">{{ $suppliers->links('vendor.pagination.simple-default') }}</div>
  @endif
</div>
@endsection
