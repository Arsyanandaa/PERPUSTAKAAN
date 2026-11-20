/**
 * auth.js — Logika Otentikasi & Session UI
 * Digunakan di seluruh halaman untuk sinkronisasi Navbar dan tombol Logout,
 * serta menangani form submit pada login.html dan register.html.
 */

document.addEventListener('DOMContentLoaded', async () => {
  // 1. Inisialisasi Navbar Auth State di semua halaman
  await initNavbarAuth();

  // 2. Pasang listener tombol logout jika ada di halaman
  document.querySelectorAll('[data-action="logout"], #btn-logout, #btn-logout-nav').forEach(btn => {
    btn.addEventListener('click', handleLogout);
  });

  // 3. Form Login (jika ada)
  const formLogin = document.getElementById('form-login');
  if (formLogin) {
    formLogin.addEventListener('submit', handleLogin);
  }

  // 4. Form Register (jika ada)
  const formRegister = document.getElementById('form-register');
  if (formRegister) {
    formRegister.addEventListener('submit', handleRegister);
  }
});

/**
 * Memeriksa session saat ini via GET /auth/me dan mengupdate navbar di semua halaman
 */
async function initNavbarAuth() {
  const navbarLinks = document.getElementById('navbar-links');
  if (!navbarLinks) return;

  const res = await Api.getMe();
  const user = res.data;

  const currentPath = window.location.pathname;
  const isAuthPage = currentPath.endsWith('login.html') || currentPath.endsWith('register.html');

  if (user) {
    // Jika sudah login dan membuka login.html atau register.html, arahkan ke dashboard
    if (isAuthPage) {
      window.location.href = user.role === 'admin' ? 'admin.html' : 'member.html';
      return;
    }

    const tierLabel = user.role === 'admin' 
      ? 'Admin' 
      : (user.tier === 'premium' ? '★ Premium' : 'Member Reguler');

    const isCatalog = currentPath.endsWith('index.html') || currentPath.endsWith('/') || currentPath === '';
    const isMemberDashboard = currentPath.endsWith('member.html');
    const isAdminPanel = currentPath.endsWith('admin.html');

    navbarLinks.innerHTML = `
      <a href="index.html" class="navbar__link ${isCatalog ? 'navbar__link--active' : ''}">Katalog</a>
      ${user.role === 'admin' 
        ? `<a href="admin.html" class="navbar__link ${isAdminPanel ? 'navbar__link--active' : ''}">Admin Panel</a>` 
        : `<a href="member.html" class="navbar__link ${isMemberDashboard ? 'navbar__link--active' : ''}">Dashboard</a>`
      }
      <div class="navbar__user">
        <div>
          <p class="navbar__user-name" id="user-display-name">${escapeHtml(user.name)}</p>
          <span class="navbar__user-role" id="user-display-tier">${tierLabel}</span>
        </div>
        <button type="button" class="btn btn--outline btn--sm btn--nav" id="btn-logout-nav" data-action="logout">
          Keluar
        </button>
      </div>
    `;

    document.getElementById('btn-logout-nav')?.addEventListener('click', handleLogout);
  } else {
    // Tamu (Belum login)
    if (currentPath.endsWith('member.html') || currentPath.endsWith('admin.html')) {
      window.location.href = 'login.html';
      return;
    }

    const isCatalog = currentPath.endsWith('index.html') || currentPath.endsWith('/') || currentPath === '';
    const isLogin = currentPath.endsWith('login.html');
    const isRegister = currentPath.endsWith('register.html');

    navbarLinks.innerHTML = `
      <a href="index.html" class="navbar__link ${isCatalog ? 'navbar__link--active' : ''}">Beranda</a>
      <a href="login.html" class="navbar__link ${isLogin ? 'navbar__link--active' : ''}">Masuk</a>
      <a href="register.html" class="btn btn--outline btn--sm btn--nav ${isRegister ? 'btn--primary' : ''}">Daftar</a>
    `;
  }
}

/**
 * Handle submit Login
 */
async function handleLogin(e) {
  e.preventDefault();
  const form = e.target;
  const btn = document.getElementById('btn-login');
  Api.clearAlert('login-alert');
  Validator.clearErrors(form);

  const data = {
    email: form.email.value,
    password: form.password.value,
  };

  const validation = Validator.validateLogin(data);
  if (!validation.isValid) {
    Validator.displayErrors(validation.errors, form);
    return;
  }

  btn.disabled = true;
  btn.textContent = 'Memproses...';

  try {
    const res = await Api.post('/auth/login', data);
    if (res.success) {
      Api.showAlert('login-alert', 'Login berhasil! Mengalihkan...', 'success');
      setTimeout(() => {
        window.location.href = res.data.role === 'admin' ? 'admin.html' : 'member.html';
      }, 700);
    } else {
      Api.showAlert('login-alert', res.message || 'Email atau kata sandi salah', 'error');
      if (res.errors) {
        Validator.displayErrors(res.errors, form);
      }
    }
  } finally {
    btn.disabled = false;
    btn.textContent = 'Masuk';
  }
}

/**
 * Handle submit Register
 */
async function handleRegister(e) {
  e.preventDefault();
  const form = e.target;
  const btn = document.getElementById('btn-register');
  Api.clearAlert('register-alert');
  Validator.clearErrors(form);

  const data = {
    name: form.name.value,
    email: form.email.value,
    password: form.password.value,
    confirmPassword: form.confirmPassword.value,
  };

  const validation = Validator.validateRegister(data);
  if (!validation.isValid) {
    Validator.displayErrors(validation.errors, form);
    return;
  }

  btn.disabled = true;
  btn.textContent = 'Mendaftarkan...';

  try {
    const res = await Api.post('/auth/register', data);
    if (res.success) {
      Api.showAlert('register-alert', 'Pendaftaran berhasil! Mengalihkan ke login...', 'success');
      form.reset();
      setTimeout(() => {
        window.location.href = 'login.html';
      }, 1000);
    } else {
      Api.showAlert('register-alert', res.message || 'Gagal mendaftar', 'error');
      if (res.errors) {
        Validator.displayErrors(res.errors, form);
      }
    }
  } finally {
    btn.disabled = false;
    btn.textContent = 'Buat Akun';
  }
}

/**
 * Handle Logout
 */
async function handleLogout() {
  if (!confirm('Apakah Anda yakin ingin keluar?')) return;
  const res = await Api.logout();
  if (res.success) {
    window.location.href = 'index.html';
  }
}

window.handleLogout = handleLogout;
window.initNavbarAuth = initNavbarAuth;

function escapeHtml(str) {
  return String(str || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

