/**
 * catalog.js — Logika Katalog Publik (index.html)
 * Mengambil daftar media via GET /api.php/catalog?q=&category=&type=
 * Mengambil kategori via GET /api.php/catalog/categories
 * Debounce pencarian 300ms, modal/konfirmasi pinjam untuk member.
 */

let currentUser = null;
let searchDebounceTimer = null;

document.addEventListener('DOMContentLoaded', async () => {
  // 1. Dapatkan user yang sedang login
  const meRes = await Api.getMe();
  currentUser = meRes.data;

  // 2. Muat kategori unik ke dropdown filter
  await loadCategories();

  // 3. Muat media awal
  await fetchAndRenderCatalog();

  // 4. Pasang event listener pencarian langsung (debounce 300ms)
  const qInput = document.getElementById('q');
  if (qInput) {
    qInput.addEventListener('input', () => {
      clearTimeout(searchDebounceTimer);
      searchDebounceTimer = setTimeout(() => {
        fetchAndRenderCatalog();
      }, 300);
    });
  }

  // 5. Filter category & type change
  document.getElementById('filter-category')?.addEventListener('change', () => fetchAndRenderCatalog());
  document.getElementById('filter-type')?.addEventListener('change', () => fetchAndRenderCatalog());

  // 6. Form submit prevent reload
  const filterForm = document.querySelector('form[data-action="filter-catalog"]');
  if (filterForm) {
    filterForm.addEventListener('submit', (e) => {
      e.preventDefault();
      fetchAndRenderCatalog();
    });
  }
});

/**
 * Muat daftar kategori dari backend
 */
async function loadCategories() {
  const select = document.getElementById('filter-category');
  if (!select) return;

  const res = await Api.get('/catalog/categories');
  if (res.success && Array.isArray(res.data)) {
    // Simpan pilihan saat ini jika ada
    const curVal = select.value;
    select.innerHTML = '<option value="">Semua Kategori</option>';
    res.data.forEach(cat => {
      const opt = document.createElement('option');
      opt.value = cat;
      opt.textContent = cat;
      if (cat === curVal) opt.selected = true;
      select.appendChild(opt);
    });
  }
}

/**
 * Ambil data media dengan filter dan render ke DOM
 */
async function fetchAndRenderCatalog() {
  const grid = document.getElementById('catalog-grid');
  const countEl = document.getElementById('catalog-count');
  if (!grid) return;

  const q = document.getElementById('q')?.value?.trim() || '';
  const category = document.getElementById('filter-category')?.value || '';
  const type = document.getElementById('filter-type')?.value || '';

  const res = await Api.get('/catalog', { q, category, type });

  if (!res.success) {
    Api.showAlert('catalog-alert', res.message || 'Gagal memuat katalog', 'error');
    return;
  }

  Api.clearAlert('catalog-alert');
  const items = res.data || [];
  if (countEl) countEl.textContent = items.length;

  if (items.length === 0) {
    grid.innerHTML = `
      <div class="empty-state" style="grid-column: 1 / -1;">
        <div class="empty-state__icon">🔍</div>
        <h3 class="empty-state__title">Media Tidak Ditemukan</h3>
        <p class="empty-state__desc">Coba sesuaikan kata kunci atau filter pencarian Anda.</p>
      </div>
    `;
    return;
  }

  grid.innerHTML = items.map(item => renderMediaCard(item)).join('');

  // Pasang listener tombol Pinjam
  grid.querySelectorAll('button[data-action="borrow"]').forEach(btn => {
    btn.addEventListener('click', () => handleBorrow(btn.dataset.id, btn.dataset.title));
  });
}

/**
 * Render HTML untuk sebuah kartu media
 */
function renderMediaCard(item) {
  const isAvailable = item.stock > 0;
  let typeBadge = '';
  let metaDetails = '';

  switch (item.type) {
    case 'ebook':
      typeBadge = '<span class="badge badge--ebook">E-Book</span>';
      metaDetails = `${item.extra?.pages || 0} hal · ${escapeHtml(item.extra?.format || 'PDF')}`;
      break;
    case 'audiobook':
      typeBadge = '<span class="badge badge--audiobook">Audiobook</span>';
      metaDetails = `${item.extra?.durationMinutes || 0} mnt · Narator: ${escapeHtml(item.extra?.narrator || '-')}`;
      break;
    case 'journal':
      typeBadge = '<span class="badge badge--journal">Jurnal</span>';
      metaDetails = `${escapeHtml(item.extra?.volume || '')} · ${escapeHtml(item.extra?.publisher || '')}`;
      break;
    default:
      typeBadge = `<span class="badge">${item.type}</span>`;
  }

  let stockClass = '';
  if (item.stock === 0) stockClass = 'stock-pill--out';
  else if (item.stock <= 2) stockClass = 'stock-pill--low';

  // Action button: Login jika belum, Pinjam jika member, tombol non-aktif jika stok habis
  let actionBtn = '';
  if (!currentUser) {
    actionBtn = `<a href="login.html" class="btn btn--outline btn--sm">Pinjam</a>`;
  } else if (currentUser.role === 'admin') {
    actionBtn = `<a href="admin.html" class="btn btn--ghost btn--sm">Kelola di Admin</a>`;
  } else if (!isAvailable) {
    actionBtn = `<button class="btn btn--ghost btn--sm" disabled>Stok Habis</button>`;
  } else {
    actionBtn = `
      <button type="button" class="btn btn--primary btn--sm" data-action="borrow" data-id="${item.id}" data-title="${escapeHtml(item.title)}">
        Pinjam
      </button>
    `;
  }

  return `
    <article class="media-card" data-type="${item.type}" data-id="${item.id}">
      <div class="media-card__header">
        ${typeBadge}
        <span class="stock-pill ${stockClass}">Stok: ${item.stock}</span>
      </div>
      <div>
        <h2 class="media-card__title">${escapeHtml(item.title)}</h2>
        <p class="media-card__author">${escapeHtml(item.author)}</p>
      </div>
      <div class="media-card__meta">
        <span>${escapeHtml(item.category)}</span>
        <span class="media-card__meta-sep" aria-hidden="true">·</span>
        <span>${item.year}</span>
        <span class="media-card__meta-sep" aria-hidden="true">·</span>
        <span>${metaDetails}</span>
      </div>
      <div class="media-card__footer">
        <div class="media-card__fee">Denda <strong>${Api.formatRupiah(item.fee)}</strong>/hari</div>
        ${actionBtn}
      </div>
    </article>
  `;
}

/**
 * Handle aksi peminjaman buku
 */
async function handleBorrow(mediaId, title) {
  if (!confirm(`Konfirmasi peminjaman:\n"${title}"\nLanjutkan meminjam media ini?`)) {
    return;
  }

  const res = await Api.post('/loans', { mediaId });

  if (res.success) {
    Api.showAlert('catalog-alert', `Berhasil meminjam "${title}"! Silakan cek di Dashboard Member.`, 'success');
    // Refresh katalog agar stok terupdate
    await fetchAndRenderCatalog();
  } else {
    Api.showAlert('catalog-alert', res.message || 'Gagal meminjam media.', 'error');
  }
}
