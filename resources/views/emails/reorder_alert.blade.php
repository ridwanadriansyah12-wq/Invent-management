<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Peringatan Reorder Point (ROP)</title>
  <style>
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
      background-color: #f8fafc;
      color: #1e293b;
      margin: 0;
      padding: 24px;
      line-height: 1.5;
    }
    .email-card {
      max-width: 600px;
      margin: 0 auto;
      background: #ffffff;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
      border: 1px solid #e2e8f0;
      overflow: hidden;
    }
    .email-header {
      background: #0f172a;
      color: #ffffff;
      padding: 24px;
      text-align: left;
    }
    .email-header h2 {
      margin: 0 0 6px 0;
      font-size: 20px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .badge-urgent {
      display: inline-block;
      background: #ef4444;
      color: #ffffff;
      padding: 3px 8px;
      border-radius: 4px;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
    }
    .email-body {
      padding: 24px;
    }
    .alert-banner {
      background-color: #fef2f2;
      border-left: 4px solid #ef4444;
      padding: 14px 16px;
      border-radius: 4px;
      margin-bottom: 20px;
      font-size: 14px;
      color: #991b1b;
    }
    .item-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 24px;
    }
    .item-table th, .item-table td {
      padding: 12px 14px;
      text-align: left;
      border-bottom: 1px solid #f1f5f9;
      font-size: 14px;
    }
    .item-table th {
      background: #f8fafc;
      color: #64748b;
      font-weight: 600;
      width: 45%;
    }
    .item-table td {
      color: #0f172a;
      font-weight: 500;
    }
    .val-highlight {
      font-size: 16px;
      font-weight: 700;
      color: #dc2626;
    }
    .val-rec {
      font-size: 16px;
      font-weight: 700;
      color: #2563eb;
    }
    .badge-tag {
      display: inline-block;
      padding: 2px 8px;
      border-radius: 4px;
      font-size: 12px;
      font-weight: 600;
    }
    .badge-override {
      background: #fef3c7;
      color: #92400e;
    }
    .badge-ml {
      background: #e0e7ff;
      color: #3730a3;
    }
    .action-container {
      text-align: center;
      margin-top: 24px;
      padding-top: 16px;
      border-top: 1px solid #f1f5f9;
    }
    .btn-action {
      display: inline-block;
      background-color: #2563eb;
      color: #ffffff !important;
      text-decoration: none;
      padding: 12px 24px;
      border-radius: 6px;
      font-weight: 600;
      font-size: 14px;
    }
    .email-footer {
      background-color: #f8fafc;
      padding: 16px 24px;
      text-align: center;
      font-size: 12px;
      color: #94a3b8;
      border-top: 1px solid #e2e8f0;
    }
  </style>
</head>
<body>
  <div class="email-card">
    <div class="email-header">
      <span class="badge-urgent">Action Required</span>
      <h2 style="margin-top: 8px;">Peringatan Reorder Point (ROP)</h2>
      <p style="margin: 0; font-size: 13px; color: #94a3b8;">Sistem Inventaris Otomatis mendeteksi stok barang telah mencapai batas pemesanan ulang.</p>
    </div>

    <div class="email-body">
      <div class="alert-banner">
        Stok fisik barang <strong>[{{ $alertData['item_code'] }}] {{ $alertData['item_name'] }}</strong> saat ini bernilai <strong>{{ number_format($alertData['current_stock'], 2) }} {{ $alertData['unit'] }}</strong>, berada pada atau di bawah batas ROP (<strong>{{ number_format($alertData['rop_value'], 2) }} {{ $alertData['unit'] }}</strong>).
      </div>

      <table class="item-table">
        <tr>
          <th>ID & Kode Barang</th>
          <td><strong>#{{ $alertData['item_id'] }}</strong> &mdash; <code>{{ $alertData['item_code'] }}</code></td>
        </tr>
        <tr>
          <th>Nama Barang</th>
          <td>{{ $alertData['item_name'] }}</td>
        </tr>
        <tr>
          <th>Stok Saat Ini</th>
          <td><span class="val-highlight">{{ number_format($alertData['current_stock'], 2) }} {{ $alertData['unit'] }}</span></td>
        </tr>
        <tr>
          <th>Batas Reorder Point (ROP)</th>
          <td><strong>{{ number_format($alertData['rop_value'], 2) }} {{ $alertData['unit'] }}</strong></td>
        </tr>
        <tr>
          <th>Rekomendasi Reorder Qty</th>
          <td><span class="val-rec">{{ number_format($alertData['recommended_qty'], 2) }} {{ $alertData['unit'] }}</span></td>
        </tr>
        <tr>
          <th>Sumber Ambang Batas</th>
          <td>
            @if($alertData['is_manual_override'])
              <span class="badge-tag badge-override">👤 Manual Override (Human-in-the-Loop)</span>
            @else
              <span class="badge-tag badge-ml">🤖 Prediksi Machine Learning</span>
            @endif
          </td>
        </tr>
      </table>

      <div class="action-container">
        <a href="{{ url('/purchase-orders/create?item_id=' . $alertData['item_id']) }}" class="btn-action">
          Buat Purchase Order Baru &rarr;
        </a>
      </div>
    </div>

    <div class="email-footer">
      Email ini dikirimkan secara otomatis oleh Sistem Inventaris Procurement berbasis Prediksi ML.<br>
      Waktu Pengecekan: {{ now()->translatedFormat('d F Y H:i:s') }} WIB
    </div>
  </div>
</body>
</html>
