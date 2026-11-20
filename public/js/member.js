/**
 * member.js — Logika Dashboard Member (member.html)
 * Menampilkan ringkasan akun member, statistik limit,
 * daftar pinjaman aktif + aksi kembalikan, riwayat transaksi,
 * dan simulasi upgrade ke status Premium.
 */

let memberData = null;

document.addEventListener('DOMContentLoaded', async () => {
  // 1. Muat profil user dari API
  await loadMemberProfile();

  // 2. Muat daftar transaksi pinjaman
  await loadMemberLoans();

  // 3. Tombol Upgrade Premium
  const btnUpgrade = document.getElementById('btn-upgrade');
  if (btnUpgrade) {
    btnUpgrade.addEventListener('click', handleUpgradePremium);
  }
});

/**
 * Muat profil user dari session
 */
async function loadMemberProfile() {
  const res = await Api.getMe();
  memberData = res.data;

  if (!memberData || memberData.role !== 'member') {
    window.location.href = 'login.html';
    return;
  }

  // Tampilkan data ke elemen UI
  document.getElementById('member-name').textContent = memberData.name;
  document.getElementById('member-email').textContent = memberData.email;

  // Sinkronkan juga info user di navbar jika elemen ada
  const userDispName = document.getElementById('user-display-name');
  if (userDispName) userDispName.textContent = memberData.name;

  const isPremium = memberData.tier === 'premium';
  const userDispTier = document.getElementById('user-display-tier');
  if (userDispTier) userDispTier.textContent = isPremium ? '★ Premium' : 'Member Reguler';

  const badgeTier = document.getElementById('badge-tier');
  badgeTier.className = `badge ${isPremium ? 'badge--premium' : 'badge--regular'}`;
  badgeTier.textContent = `Tier: ${isPremium ? '★ Premium' : 'Reguler'}`;

  const badgeStatus = document.getElementById('badge-status');
  const isActive = memberData.status === 'active';
  badgeStatus.className = `badge ${isActive ? 'badge--active' : 'badge--inactive'}`;
  badgeStatus.textContent = `Status: ${isActive ? 'Aktif' : 'Nonaktif'}`;

  const joinedEl = document.getElementById('member-joined');
  if (joinedEl && memberData.joinedAt) {
    joinedEl.textContent = `Bergabung sejak ${memberData.joinedAt}`;
  }

  document.getElementById('stat-borrow-limit').innerHTML = `${memberData.borrowLimit} <span>item</span>`;
  document.getElementById('stat-loan-days').innerHTML = `${memberData.loanDays} <span>hari</span>`;
  document.getElementById('stat-fee-discount').innerHTML = isPremium 
    ? `<span style="color:var(--clr-green-mid);">Diskon 50%</span> <span>(0.5x)</span>`
    : `Standar <span>(1.0x)</span>`;

  // Sembunyikan tombol upgrade jika sudah premium
  const upgradeSec = document.getElementById('upgrade-section');
  if (upgradeSec && isPremium) {
    upgradeSec.innerHTML = `
      <span class="badge badge--premium" style="font-size:13px; padding:6px 12px;">
        ★ Anggota Premium Aktif
      </span>
      <span class="form-hint" style="text-align:right;">Anda menikmati kuota maksimal 10 buku dan diskon 50% denda.</span>
    `;
  }
}

/**
 * Muat transaksi peminjaman (aktif & riwayat selesai)
 */
async function loadMemberLoans() {
  const res = await Api.get('/loans');
  if (!res.success) {
    Api.showAlert('member-alert', res.message || 'Gagal memuat daftar peminjaman', 'error');
    return;
  }

  const loans = res.data || [];
  const activeLoans = loans.filter(l => l.status === 'active');
  const historyLoans = loans.filter(l => l.status === 'completed');

  // Render Pinjaman Aktif
  renderActiveLoans(activeLoans);

  // Render Riwayat Selesai
  renderHistoryLoans(historyLoans);
}

function renderActiveLoans(loans) {
  const tbody = document.getElementById('tbody-active-loans');
  const countEl = document.getElementById('count-active-loans');
  if (countEl) countEl.textContent = `${loans.length} item aktif`;

  if (!tbody) return;

  if (loans.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="7" class="text-muted" style="text-align:center; padding:var(--sp-6);">
          Tidak ada pinjaman buku yang sedang aktif saat ini.
        </td>
      </tr>
    `;
    return;
  }

  tbody.innerHTML = loans.map(loan => {
    const isOverdue = loan.isOverdue || loan.daysLate > 0;
    const statusBadge = isOverdue 
      ? `<span class="badge badge--overdue">Terlambat ${loan.daysLate} hari (Estimasi Denda: ${Api.formatRupiah(loan.fee)})</span>`
      : `<span class="badge badge--active">Aktif</span>`;

    return `
      <tr data-loan-id="${loan.id}">
        <td><code>${loan.id}</code></td>
        <td><strong>${escapeHtml(loan.mediaTitle)}</strong></td>
        <td><span class="badge badge--${loan.mediaType}">${loan.mediaType}</span></td>
        <td>${loan.borrowedAt}</td>
        <td><span class="${isOverdue ? 'text-red fw-bold' : ''}">${loan.dueAt}</span></td>
        <td>${statusBadge}</td>
        <td style="text-align:right;">
          <button type="button" class="btn btn--primary btn--sm" data-action="return" data-loan-id="${loan.id}" data-title="${escapeHtml(loan.mediaTitle)}">
            Kembalikan
          </button>
        </td>
      </tr>
    `;
  }).join('');

  // Pasang listener pengembalian
  tbody.querySelectorAll('button[data-action="return"]').forEach(btn => {
    btn.addEventListener('click', () => handleReturn(btn.dataset.loanId, btn.dataset.title));
  });
}

function renderHistoryLoans(loans) {
  const tbody = document.getElementById('tbody-history-loans');
  const countEl = document.getElementById('count-history-loans');
  if (countEl) countEl.textContent = `${loans.length} riwayat`;

  if (!tbody) return;

  if (loans.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="8" class="text-muted" style="text-align:center; padding:var(--sp-6);">
          Belum ada riwayat transaksi pengembalian.
        </td>
      </tr>
    `;
    return;
  }

  tbody.innerHTML = loans.map(loan => {
    const lateText = loan.daysLate > 0 
      ? `<span class="text-red fw-semi">Terlambat ${loan.daysLate} hari</span>`
      : 'Tepat Waktu (0 hari)';

    return `
      <tr>
        <td><code>${loan.id}</code></td>
        <td>${escapeHtml(loan.mediaTitle)}</td>
        <td><span class="badge badge--${loan.mediaType}">${loan.mediaType}</span></td>
        <td>${loan.borrowedAt}</td>
        <td>${loan.returnedAt || '-'}</td>
        <td>${lateText}</td>
        <td><strong>${Api.formatRupiah(loan.fee)}</strong></td>
        <td><span class="badge badge--completed">Selesai</span></td>
      </tr>
    `;
  }).join('');
}

/**
 * Handle aksi pengembalian buku
 */
async function handleReturn(loanId, title) {
  if (!confirm(`Konfirmasi pengembalian media:\n"${title}"\nApakah Anda yakin ingin mengembalikan item ini sekarang?`)) {
    return;
  }

  const res = await Api.post(`/loans/${loanId}/return`);
  if (res.success) {
    const feeMsg = res.data?.fee > 0 
      ? ` Denda keterlambatan: ${Api.formatRupiah(res.data.fee)}` 
      : ' Pengembalian tepat waktu tanpa denda.';
    Api.showAlert('member-alert', `Media berhasil dikembalikan!${feeMsg}`, 'success');
    
    // Refresh tabel transaksi
    await loadMemberLoans();
  } else {
    Api.showAlert('member-alert', res.message || 'Gagal mengembalikan media', 'error');
  }
}

/**
 * Handle simulasi upgrade ke Premium
 */
async function handleUpgradePremium() {
  if (!confirm('Apakah Anda ingin meng-upgrade akun ke status Premium?\nManfaat: Kuota 10 buku, masa pinjam 14 hari, dan diskon denda 50%.')) {
    return;
  }

  const res = await Api.post('/members/upgrade');
  if (res.success) {
    Api.showAlert('member-alert', 'Selamat! Akun Anda berhasil di-upgrade ke status Premium!', 'success');
    await loadMemberProfile();
  } else {
    Api.showAlert('member-alert', res.message || 'Gagal melakukan upgrade', 'error');
  }
}
