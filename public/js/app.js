/* ========================================================
   Inventory ROP – Application JavaScript
   ======================================================== */

document.addEventListener('DOMContentLoaded', () => {

  // ── Sidebar Toggle (Mobile) ─────────────────────────────────────────────
  const sidebar  = document.getElementById('sidebar');
  const overlay  = document.getElementById('sidebarOverlay');
  const menuBtn  = document.getElementById('menuToggle');

  function openSidebar()  { sidebar?.classList.add('open'); overlay?.classList.remove('hidden'); }
  function closeSidebar() { sidebar?.classList.remove('open'); overlay?.classList.add('hidden'); }

  menuBtn?.addEventListener('click', openSidebar);
  overlay?.addEventListener('click', closeSidebar);

  // ── Auto-dismiss flash alerts ────────────────────────────────────────────
  document.querySelectorAll('.alert').forEach(el => {
    const close = el.querySelector('.alert-close');
    close?.addEventListener('click', () => el.remove());
    setTimeout(() => el.style.opacity === '' && el.remove(), 6000);
  });

  // ── Delete Confirm Modal ─────────────────────────────────────────────────
  const deleteModal   = document.getElementById('deleteModal');
  const deleteTitle   = document.getElementById('deleteTitle');
  const deleteMsg     = document.getElementById('deleteMsg');
  const deleteConfirm = document.getElementById('deleteConfirm');
  const deleteCancel  = document.getElementById('deleteCancel');
  let   deletePending = null;

  document.querySelectorAll('[data-delete]').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      deleteTitle && (deleteTitle.textContent = btn.dataset.deleteTitle || 'Hapus Data');
      deleteMsg   && (deleteMsg.textContent   = btn.dataset.deleteMsg   || 'Apakah Anda yakin ingin menghapus data ini?');
      deletePending = btn.closest('form') || btn.nextElementSibling;
      deleteModal?.classList.add('open');
    });
  });

  deleteCancel?.addEventListener('click',  () => { deleteModal?.classList.remove('open'); deletePending = null; });
  deleteConfirm?.addEventListener('click', () => {
    if (deletePending) {
      if (deletePending instanceof HTMLFormElement) deletePending.submit();
      else deletePending.querySelector('form')?.submit();
    }
    deleteModal?.classList.remove('open');
  });
  deleteModal?.addEventListener('click', (e) => {
    if (e.target === deleteModal) deleteModal.classList.remove('open');
  });

  // ── Receive Modal ────────────────────────────────────────────────────────
  const receiveModal   = document.getElementById('receiveModal');
  const receiveCancel  = document.getElementById('receiveCancel');
  document.querySelectorAll('[data-receive]').forEach(btn => {
    btn.addEventListener('click', () => receiveModal?.classList.add('open'));
  });
  receiveCancel?.addEventListener('click', () => receiveModal?.classList.remove('open'));
  receiveModal?.addEventListener('click', (e) => {
    if (e.target === receiveModal) receiveModal.classList.remove('open');
  });

  // ── Notification Badge ───────────────────────────────────────────────────
  async function refreshNotifCount() {
    try {
      const res   = await fetch('/notifications/unread-count', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      const data  = await res.json();
      const count = data.count || 0;
      const badge = document.getElementById('notifCount');
      const dot   = document.getElementById('notifDot');
      if (badge) { badge.textContent = count > 99 ? '99+' : count; badge.classList.toggle('active', count > 0); }
      if (dot)   { dot.classList.toggle('active', count > 0); }
    } catch (e) {}
  }
  refreshNotifCount();
  setInterval(refreshNotifCount, 60000); // refresh every 60s

  // ── Item code auto-uppercase ─────────────────────────────────────────────
  document.getElementById('item_code')?.addEventListener('input', function() {
    this.value = this.value.toUpperCase().replace(/[^A-Z0-9\-_]/g, '');
  });

  // ── Supplier auto-fill from item selection ────────────────────────────────
  const itemSelect    = document.getElementById('item_select');
  const supplierSelect = document.getElementById('supplier_id');
  const itemData      = window.ITEMS_DATA || {};

  itemSelect?.addEventListener('change', function() {
    const item = itemData[this.value];
    if (item && supplierSelect && item.supplier_id) {
      supplierSelect.value = item.supplier_id;
    }
    if (item && document.getElementById('suggested_qty')) {
      document.getElementById('suggested_qty').textContent = item.recommended_order_qty || '-';
    }
    if (item && document.getElementById('current_stock')) {
      document.getElementById('current_stock').textContent = item.stock_on_hand + ' ' + item.unit;
    }
    if (item && document.getElementById('item_rop')) {
      document.getElementById('item_rop').textContent = item.rop;
    }
    if (item && document.getElementById('item_max')) {
      document.getElementById('item_max').textContent = item.max_stock;
    }
  });

  // ── Chart.js Defaults ────────────────────────────────────────────────────
  if (window.Chart) {
    Chart.defaults.color          = '#64748b';
    Chart.defaults.borderColor    = '#e2e8f0';
    Chart.defaults.font.family    = 'Inter, system-ui, sans-serif';
    Chart.defaults.font.size      = 12;

    Chart.defaults.plugins.legend.labels.color     = '#475569';
    Chart.defaults.plugins.legend.labels.boxWidth   = 10;
    Chart.defaults.plugins.legend.labels.padding    = 16;
    Chart.defaults.plugins.tooltip.backgroundColor  = '#0f172a';
    Chart.defaults.plugins.tooltip.borderColor       = '#334155';
    Chart.defaults.plugins.tooltip.borderWidth       = 1;
    Chart.defaults.plugins.tooltip.titleColor        = '#ffffff';
    Chart.defaults.plugins.tooltip.bodyColor         = '#cbd5e1';
    Chart.defaults.plugins.tooltip.padding           = 10;
  }

  // ── Stock Level Progress ─────────────────────────────────────────────────
  document.querySelectorAll('[data-stock-progress]').forEach(el => {
    const pct = parseFloat(el.dataset.stockProgress) || 0;
    const bar = el.querySelector('.progress-bar');
    if (bar) {
      bar.style.width = Math.min(100, pct) + '%';
      if (pct < 20)       bar.classList.add('danger');
      else if (pct < 50)  bar.classList.add('warning');
      else                bar.classList.add('success');
    }
  });

  // ── Quantity validation for transactions ──────────────────────────────────
  const qtyInput    = document.getElementById('quantity');
  const maxStockVal = document.getElementById('max_stock_val');

  if (qtyInput && maxStockVal) {
    qtyInput.addEventListener('input', function () {
      const max = parseFloat(maxStockVal.textContent) || Infinity;
      const val = parseFloat(this.value) || 0;
      if (val > max) this.classList.add('is-invalid');
      else this.classList.remove('is-invalid');
    });
  // ── Toast Notification Helper ───────────────────────────────────────────
  window.showToast = function(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast-item toast-${type}`;

    const icon = type === 'success' ? '✅' : (type === 'error' ? '❌' : '⚠️');
    toast.innerHTML = `
      <div style="display:flex;align-items:center;gap:8px;">
        <span>${icon}</span>
        <span>${message}</span>
      </div>
      <button class="toast-close">&times;</button>
    `;

    toast.querySelector('.toast-close').addEventListener('click', () => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateX(40px)';
      setTimeout(() => toast.remove(), 300);
    });

    container.appendChild(toast);

    setTimeout(() => {
      if (toast.parentNode) {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(40px)';
        setTimeout(() => toast.remove(), 300);
      }
    }, 4000);
  };
});
