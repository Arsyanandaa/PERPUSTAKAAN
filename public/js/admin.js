/**
 * admin.js — Logika Panel Administrator (admin.html)
 * Tab switcher:
 * 1. Manajemen Media Katalog: CRUD (tambah, edit, hapus) + modal interaktif
 * 2. Manajemen Member: Daftar member + toggle status (aktif/nonaktif)
 * 3. Semua Transaksi: Riwayat peminjaman seluruh anggota
 */

let allMedia = [];
let editingMediaId = null;

document.addEventListener('DOMContentLoaded', async () => {
  // 1. Verifikasi role admin
  const meRes = await Api.getMe();
  if (!meRes.data || meRes.data.role !== 'admin') {
    window.location.href = 'login.html';
    return;
  }

  // 2. Tab Navigation
  initTabs();

  // 3. Modal Form Media
  initMediaModal();

  // 4. Muat data awal
  await loadAdminMedia();
  await loadAdminMembers();
  await loadAdminLoans();

  // 5. Pencarian tabel media admin
  const searchInput = document.getElementById('search-admin-media');
  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      const q = e.target.value.toLowerCase().trim();
      const filtered = allMedia.filter(m => 
        m.title.toLowerCase().includes(q) || 
        m.author.toLowerCase().includes(q) ||
        m.category.toLowerCase().includes(q)
      );
      renderAdminMediaTable(filtered);
    });
  }
});

/**
 * Inisialisasi perpindahan tab
 */
function initTabs() {
  const tabs = [
    { btn: 'tab-catalog-btn', panel: 'panel-catalog' },
    { btn: 'tab-members-btn', panel: 'panel-members' },
    { btn: 'tab-loans-btn', panel: 'panel-loans' },
  ];

  tabs.forEach(t => {
    const btn = document.getElementById(t.btn);
    if (!btn) return;
    btn.addEventListener('click', () => {
      tabs.forEach(x => {
        document.getElementById(x.btn)?.classList.remove('is-active');
        document.getElementById(x.btn)?.setAttribute('aria-selected', 'false');
        document.getElementById(x.panel)?.classList.add('is-hidden');
      });
      btn.classList.add('is-active');
      btn.setAttribute('aria-selected', 'true');
      document.getElementById(t.panel)?.classList.remove('is-hidden');
    });
  });
}

/**
 * Inisialisasi Modal Tambah / Ubah Media
 */
function initMediaModal() {
  const modal = document.getElementById('modal-media');
  const btnOpen = document.getElementById('btn-open-add-media');
  const typeSelect = document.getElementById('media-type');
  const form = document.getElementById('form-media');

  // Buka modal tambah
  btnOpen?.addEventListener('click', () => {
    editingMediaId = null;
    form.reset();
    Validator.clearErrors(form);
    document.getElementById('modal-media-title').textContent = 'Tambah Media Baru';
    typeSelect.disabled = false;
    document.getElementById('hint-media-type').style.display = 'none';
    updateExtraFields('ebook');
    modal.classList.remove('is-hidden');
  });

  // Tutup modal
  modal?.querySelectorAll('[data-action="close-modal"]').forEach(el => {
    el.addEventListener('click', () => modal.classList.add('is-hidden'));
  });

  // Pergantian extra fields saat type berubah
  typeSelect?.addEventListener('change', (e) => {
    updateExtraFields(e.target.value);
  });

  // Submit form media
  form?.addEventListener('submit', handleSaveMedia);
}

function updateExtraFields(type) {
  document.getElementById('extra-ebook').style.display = type === 'ebook' ? 'block' : 'none';
  document.getElementById('extra-audiobook').style.display = type === 'audiobook' ? 'block' : 'none';
  document.getElementById('extra-journal').style.display = type === 'journal' ? 'block' : 'none';
}

/**
 * -------------------------------------------------------------
 * 1. Manajemen Media
 * -------------------------------------------------------------
 */
async function loadAdminMedia() {
  const res = await Api.get('/catalog');
  if (res.success) {
    allMedia = res.data || [];
    renderAdminMediaTable(allMedia);
  }
}

function renderAdminMediaTable(mediaList) {
  const tbody = document.getElementById('tbody-admin-media');
  if (!tbody) return;

  if (mediaList.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="9" class="text-muted" style="text-align:center; padding:var(--sp-6);">
          Tidak ada media yang cocok.
        </td>
      </tr>
    `;
    return;
  }

  tbody.innerHTML = mediaList.map(item => `
    <tr data-id="${item.id}">
      <td><code>${item.id}</code></td>
      <td><span class="badge badge--${item.type}">${item.type}</span></td>
      <td><strong>${escapeHtml(item.title)}</strong></td>
      <td>${escapeHtml(item.author)}</td>
      <td>${escapeHtml(item.category)}</td>
      <td>${item.year}</td>
      <td>${item.stock === 0 ? '<span class="stock-pill stock-pill--out">0</span>' : item.stock}</td>
      <td>${Api.formatRupiah(item.fee)}</td>
      <td style="text-align:right;">
        <div class="row-actions" style="justify-content:flex-end;">
          <button type="button" class="btn btn--ghost btn--sm" data-action="edit-media" data-id="${item.id}">Ubah</button>
          <button type="button" class="btn btn--danger btn--sm" data-action="delete-media" data-id="${item.id}" data-title="${escapeHtml(item.title)}">Hapus</button>
        </div>
      </td>
    </tr>
  `).join('');

  // Pasang listener edit dan delete
  tbody.querySelectorAll('[data-action="edit-media"]').forEach(btn => {
    btn.addEventListener('click', () => openEditModal(btn.dataset.id));
  });

  tbody.querySelectorAll('[data-action="delete-media"]').forEach(btn => {
    btn.addEventListener('click', () => handleDeleteMedia(btn.dataset.id, btn.dataset.title));
  });
}

function openEditModal(id) {
  const item = allMedia.find(m => m.id === id);
  if (!item) return;

  editingMediaId = id;
  const form = document.getElementById('form-media');
  Validator.clearErrors(form);

  document.getElementById('modal-media-title').textContent = `Ubah Media: ${item.id}`;
  document.getElementById('media-id').value = item.id;
  
  const typeSelect = document.getElementById('media-type');
  typeSelect.value = item.type;
  typeSelect.disabled = true; // Sesuai aturan: PUT tidak boleh ubah type
  document.getElementById('hint-media-type').style.display = 'block';

  document.getElementById('media-title').value = item.title;
  document.getElementById('media-author').value = item.author;
  document.getElementById('media-category').value = item.category;
  document.getElementById('media-year').value = item.year;
  document.getElementById('media-stock').value = item.stock;
  document.getElementById('media-fee').value = item.fee;

  updateExtraFields(item.type);

  if (item.type === 'ebook') {
    document.getElementById('extra-pages').value = item.extra?.pages || '';
    document.getElementById('extra-format').value = item.extra?.format || 'PDF';
  } else if (item.type === 'audiobook') {
    document.getElementById('extra-duration').value = item.extra?.durationMinutes || '';
    document.getElementById('extra-narrator').value = item.extra?.narrator || '';
  } else if (item.type === 'journal') {
    document.getElementById('extra-publisher').value = item.extra?.publisher || '';
    document.getElementById('extra-volume').value = item.extra?.volume || '';
  }

  document.getElementById('modal-media').classList.remove('is-hidden');
}

async function handleSaveMedia(e) {
  e.preventDefault();
  const form = e.target;
  const btn = document.getElementById('btn-save-media');
  Validator.clearErrors(form);

  const type = document.getElementById('media-type').value;
  const payload = {
    title: form.title.value.trim(),
    author: form.author.value.trim(),
    category: form.category.value.trim(),
    year: Number(form.year.value),
    stock: Number(form.stock.value),
    fee: form.fee.value !== '' ? Number(form.fee.value) : 0,
    extra: {}
  };

  if (!editingMediaId) {
    payload.type = type;
  }

  if (type === 'ebook') {
    payload.extra = {
      pages: Number(document.getElementById('extra-pages').value),
      format: document.getElementById('extra-format').value
    };
  } else if (type === 'audiobook') {
    payload.extra = {
      durationMinutes: Number(document.getElementById('extra-duration').value),
      narrator: document.getElementById('extra-narrator').value.trim()
    };
  } else if (type === 'journal') {
    payload.extra = {
      publisher: document.getElementById('extra-publisher').value.trim(),
      volume: document.getElementById('extra-volume').value.trim()
    };
  }

  // Validasi Client-side
  const valResult = Validator.validateMedia(
    editingMediaId ? { ...payload, type } : payload,
    Boolean(editingMediaId)
  );

  if (!valResult.isValid) {
    Validator.displayErrors(valResult.errors, form);
    return;
  }

  btn.disabled = true;
  btn.textContent = 'Menyimpan...';

  try {
    const res = editingMediaId 
      ? await Api.put(`/catalog/${editingMediaId}`, payload)
      : await Api.post('/catalog', payload);

    if (res.success) {
      Api.showAlert('admin-alert', res.message || 'Media berhasil disimpan!', 'success');
      document.getElementById('modal-media').classList.add('is-hidden');
      await loadAdminMedia();
    } else {
      if (res.errors) {
        Validator.displayErrors(res.errors, form);
      } else {
        alert(res.message || 'Gagal menyimpan media');
      }
    }
  } finally {
    btn.disabled = false;
    btn.textContent = 'Simpan Media';
  }
}

async function handleDeleteMedia(id, title) {
  if (!confirm(`Konfirmasi Penghapusan:\nApakah Anda yakin ingin menghapus media "${title}" (ID: ${id})?\nAksi ini tidak dapat dibatalkan.`)) {
    return;
  }

  const res = await Api.delete(`/catalog/${id}`);
  if (res.success) {
    Api.showAlert('admin-alert', 'Media berhasil dihapus!', 'success');
    await loadAdminMedia();
  } else {
    Api.showAlert('admin-alert', res.message || 'Gagal menghapus media (Mungkin sedang dipinjam)', 'error');
  }
}

/**
 * -------------------------------------------------------------
 * 2. Manajemen Member
 * -------------------------------------------------------------
 */
async function loadAdminMembers() {
  const res = await Api.get('/members');
  if (!res.success) return;

  const members = res.data || [];
  const tbody = document.getElementById('tbody-admin-members');
  if (!tbody) return;

  tbody.innerHTML = members.map(m => {
    const isActive = m.status === 'active';
    return `
      <tr data-id="${m.id}">
        <td><code>${m.id}</code></td>
        <td><strong>${escapeHtml(m.name)}</strong></td>
        <td>${escapeHtml(m.email)}</td>
        <td><span class="badge badge--${m.tier}">${m.tier}</span></td>
        <td>${m.borrowLimit} item</td>
        <td>${m.loanDays} hari</td>
        <td>${m.joinedAt}</td>
        <td><span class="badge ${isActive ? 'badge--active' : 'badge--inactive'}">${isActive ? 'Active' : 'Inactive'}</span></td>
        <td style="text-align:right;">
          <button type="button" class="btn ${isActive ? 'btn--danger' : 'btn--outline'} btn--sm" 
                  data-action="toggle-status" data-id="${m.id}" data-status="${isActive ? 'inactive' : 'active'}" data-name="${escapeHtml(m.name)}">
            ${isActive ? 'Nonaktifkan' : 'Aktifkan'}
          </button>
        </td>
      </tr>
    `;
  }).join('');

  tbody.querySelectorAll('[data-action="toggle-status"]').forEach(btn => {
    btn.addEventListener('click', () => handleToggleMemberStatus(btn.dataset.id, btn.dataset.status, btn.dataset.name));
  });
}

async function handleToggleMemberStatus(id, newStatus, name) {
  const actionText = newStatus === 'active' ? 'mengaktifkan' : 'menonaktifkan';
  if (!confirm(`Apakah Anda yakin ingin ${actionText} member "${name}"?`)) {
    return;
  }

  const res = await Api.patch(`/members/${id}/status`, { status: newStatus });
  if (res.success) {
    Api.showAlert('admin-alert', res.message || 'Status member berhasil diubah!', 'success');
    await loadAdminMembers();
  } else {
    Api.showAlert('admin-alert', res.message || 'Gagal mengubah status member', 'error');
  }
}

/**
 * -------------------------------------------------------------
 * 3. Semua Transaksi
 * -------------------------------------------------------------
 */
async function loadAdminLoans() {
  const res = await Api.get('/loans');
  if (!res.success) return;

  const loans = res.data || [];
  const tbody = document.getElementById('tbody-admin-loans');
  if (!tbody) return;

  if (loans.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="8" class="text-muted" style="text-align:center; padding:var(--sp-6);">
          Belum ada data peminjaman yang tercatat di sistem.
        </td>
      </tr>
    `;
    return;
  }

  tbody.innerHTML = loans.map(loan => `
    <tr>
      <td><code>${loan.id}</code></td>
      <td><strong>${escapeHtml(loan.userName || loan.userId)}</strong></td>
      <td>${escapeHtml(loan.mediaTitle)}</td>
      <td>${loan.borrowedAt}</td>
      <td>${loan.dueAt}</td>
      <td>${loan.returnedAt || '-'}</td>
      <td>${Api.formatRupiah(loan.fee)}</td>
      <td><span class="badge badge--${loan.status}">${loan.status}</span></td>
    </tr>
  `).join('');
}
