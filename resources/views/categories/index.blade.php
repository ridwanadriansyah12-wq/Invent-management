@extends('layouts.app')
@section('title', 'Kategori')
@section('content')
<div class="page-header"><h1 class="page-title">Kategori Barang</h1></div>

<div class="d-flex gap-4" style="flex-wrap:wrap;align-items:flex-start;">
  {{-- Add Form --}}
  <div class="card" style="min-width:280px;max-width:360px;">
    <div class="card-header"><span class="card-title">Tambah Kategori</span></div>
    <div class="card-body">
      <form action="{{ route('categories.store') }}" method="POST">
        @csrf
        <div class="form-group">
          <label class="form-label">Nama Kategori <span class="required">*</span></label>
          <input type="text" name="name" class="form-control" value="{{ old('name') }}" required maxlength="100" placeholder="Nama kategori">
          @error('name') <div class="form-error">{{ $message }}</div> @enderror
        </div>
        <div class="form-group">
          <label class="form-label">Deskripsi</label>
          <input type="text" name="description" class="form-control" value="{{ old('description') }}" maxlength="255" placeholder="Opsional">
        </div>
        <button type="submit" class="btn btn-primary w-100">Tambah</button>
      </form>
    </div>
  </div>

  {{-- List --}}
  <div class="card flex-1" style="min-width:280px;">
    <div class="card-header"><span class="card-title">Daftar Kategori ({{ $categories->count() }})</span></div>
    <div class="table-container">
      <table>
        <thead><tr><th>Nama</th><th>Deskripsi</th><th>Jumlah Barang</th><th>Aksi</th></tr></thead>
        <tbody>
          @forelse($categories as $cat)
          <tr>
            <td class="font-bold">{{ $cat->name }}</td>
            <td class="text-muted text-sm">{{ $cat->description ?? '-' }}</td>
            <td><span class="badge badge-white">{{ $cat->items_count }}</span></td>
            <td>
              <div class="d-flex gap-1">
                <button type="button" class="btn btn-sm btn-secondary"
                  onclick="document.getElementById('editCat{{ $cat->id }}').classList.toggle('open')">Edit</button>
                <form action="{{ route('categories.destroy', $cat) }}" method="POST">
                  @csrf @method('DELETE')
                  <button type="button" class="btn btn-sm btn-danger" data-delete
                    data-delete-title="Hapus Kategori"
                    data-delete-msg="Hapus kategori {{ $cat->name }}? Pastikan tidak ada barang.">Hapus</button>
                </form>
              </div>
              {{-- Inline Edit --}}
              <div id="editCat{{ $cat->id }}" style="display:none;margin-top:8px;">
                <form action="{{ route('categories.update', $cat) }}" method="POST">
                  @csrf @method('PUT')
                  <input type="text" name="name" class="form-control mb-2" value="{{ $cat->name }}" required style="margin-bottom:6px;">
                  <input type="text" name="description" class="form-control mb-2" value="{{ $cat->description }}" style="margin-bottom:6px;">
                  <button type="submit" class="btn btn-sm btn-primary">Simpan</button>
                </form>
              </div>
            </td>
          </tr>
          @empty
          <tr><td colspan="4" style="text-align:center;padding:30px;color:var(--text-muted);">Belum ada kategori</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[id^="editCat"]').forEach(el => {
  el.style.display = el.classList.contains('open') ? 'block' : 'none';
});
document.querySelectorAll('button[onclick*="editCat"]').forEach(btn => {
  btn.addEventListener('click', () => {
    const id = btn.getAttribute('onclick').match(/editCat(\d+)/)[1];
    const el = document.getElementById('editCat' + id);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
  });
});
</script>
@endpush
