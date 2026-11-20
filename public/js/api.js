/**
 * api.js — PustakaKu Fetch API Wrapper
 * Menangani komunikasi HTTP ke /api.php dengan format respons terstandarisasi.
 */

const API_BASE = 'api.php';

const Api = {
  /**
   * Request generik ke backend API
   * @param {string} endpoint contoh: '/catalog' atau '/auth/login'
   * @param {string} method GET, POST, PUT, PATCH, DELETE
   * @param {object|null} body Data payload JSON
   * @param {object|null} query Parameter query string
   * @returns {Promise<{success: boolean, data?: any, message?: string, errors?: object, status: number}>}
   */
  async request(endpoint, method = 'GET', body = null, query = null) {
    let url = `${API_BASE}${endpoint.startsWith('/') ? endpoint : '/' + endpoint}`;
    
    if (query) {
      const params = new URLSearchParams();
      for (const [key, val] of Object.entries(query)) {
        if (val !== null && val !== undefined && val !== '') {
          params.append(key, val);
        }
      }
      const qs = params.toString();
      if (qs) {
        url += (url.includes('?') ? '&' : '?') + qs;
      }
    }

    const options = {
      method: method.toUpperCase(),
      headers: {
        'Accept': 'application/json',
      },
    };

    if (body !== null && ['POST', 'PUT', 'PATCH', 'DELETE'].includes(options.method)) {
      options.headers['Content-Type'] = 'application/json';
      options.body = JSON.stringify(body);
    }

    try {
      const response = await fetch(url, options);
      const isJson = response.headers.get('content-type')?.includes('application/json');
      const data = isJson ? await response.json() : null;

      if (!data) {
        return {
          success: false,
          status: response.status,
          message: response.ok ? 'Berhasil' : `Kesalahan server (${response.status})`,
        };
      }

      return {
        ...data,
        status: response.status,
      };
    } catch (err) {
      console.error('[API Error]:', err);
      return {
        success: false,
        status: 0,
        message: 'Tidak dapat terhubung ke server. Periksa koneksi Anda.',
      };
    }
  },

  get(endpoint, query = null) {
    return this.request(endpoint, 'GET', null, query);
  },

  post(endpoint, body = {}) {
    return this.request(endpoint, 'POST', body);
  },

  put(endpoint, body = {}) {
    return this.request(endpoint, 'PUT', body);
  },

  patch(endpoint, body = {}) {
    return this.request(endpoint, 'PATCH', body);
  },

  delete(endpoint) {
    return this.request(endpoint, 'DELETE');
  },

  // Auth Helpers
  async getMe() {
    return this.get('/auth/me');
  },

  async logout() {
    return this.post('/auth/logout');
  },

  /**
   * Helper format Rupiah
   */
  formatRupiah(num) {
    const val = Number(num) || 0;
    return 'Rp ' + val.toLocaleString('id-ID');
  },

  /**
   * Helper tampilkan alert/toast
   */
  showAlert(containerId, message, type = 'error') {
    const el = document.getElementById(containerId);
    if (!el) return;
    el.className = `alert alert--${type} animate-in`;
    el.textContent = message;
    el.style.display = 'flex';
  },

  clearAlert(containerId) {
    const el = document.getElementById(containerId);
    if (!el) return;
    el.textContent = '';
    el.className = 'alert';
    el.style.display = 'none';
  }
};
