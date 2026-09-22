@extends('layouts.app')
@section('title', 'Notifikasi')

@section('content')
<div class="page-header d-flex align-center justify-between">
  <div>
    <h1 class="page-title">Notifikasi</h1>
    <p class="page-sub">Peringatan stok dan status sistem</p>
  </div>
  @if($notifs->total() > 0)
  <form action="{{ route('notifications.read-all') }}" method="POST">
    @csrf
    <button type="submit" class="btn btn-secondary">✅ Tandai Semua Dibaca</button>
  </form>
  @endif
</div>

<div class="card">
  @forelse($notifs as $notif)
  <div class="notif-item {{ !$notif->is_read ? 'unread' : '' }}">
    @if(!$notif->is_read)<div class="unread-dot"></div>@endif
    <div class="notif-item-icon">{{ $notif->icon }}</div>
    <div class="notif-item-body">
      <div class="d-flex align-center gap-2 mb-1">
        <span class="notif-item-title">{{ $notif->title }}</span>
        <span class="badge {{ $notif->badge_class }}">{{ $notif->type }}</span>
      </div>
      <div class="notif-item-msg">{{ $notif->message }}</div>
      <div class="notif-item-time">{{ $notif->created_at->diffForHumans() }}</div>
    </div>
    <div class="notif-item-actions">
      @if($notif->item_id)
      <a href="{{ route('items.show', $notif->item_id) }}" class="btn btn-sm btn-secondary">Lihat</a>
      @endif
      @if(!$notif->is_read)
      <form action="{{ route('notifications.read', $notif) }}" method="POST">
        @csrf
        <button type="submit" class="btn btn-sm btn-secondary" title="Tandai dibaca">✓</button>
      </form>
      @endif
      <form action="{{ route('notifications.destroy', $notif) }}" method="POST">
        @csrf @method('DELETE')
        <button type="button" class="btn btn-sm btn-danger" data-delete data-delete-title="Hapus Notifikasi" data-delete-msg="Hapus notifikasi ini?">×</button>
      </form>
    </div>
  </div>
  @empty
  <div class="empty-state">
    <div class="empty-icon">🔔</div>
    <div class="empty-title">Tidak ada notifikasi</div>
    <div class="empty-desc">Semua stok barang dalam kondisi baik</div>
  </div>
  @endforelse
</div>

@if($notifs->hasPages())
<div style="margin-top:16px;">{{ $notifs->links('vendor.pagination.simple-default') }}</div>
@endif
@endsection
