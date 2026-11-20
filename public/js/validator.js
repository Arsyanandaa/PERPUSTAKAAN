/**
 * validator.js — Validasi Client-side PustakaKu
 * Mengikuti aturan yang sama persis dengan backend:
 * - name: 3 - 50 karakter
 * - email: format email valid
 * - password: 8 - 72 karakter, kombinasi huruf & angka
 * - confirmPassword: sama dengan password
 * - title: 1 - 150 karakter
 * - author: 1 - 100 karakter
 * - category: 1 - 50 karakter
 * - year: 1900 - tahun depan
 * - stock: 0 - 9999
 * - fee: 0 - 1.000.000
 * - extra fields per tipe media
 */

const Validator = {
  isValidEmail(email) {
    const re = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)+$/;
    return re.test(String(email).trim());
  },

  /**
   * Validasi form registrasi
   * @param {{name: string, email: string, password: string, confirmPassword: string}} data 
   * @returns {{isValid: boolean, errors: Record<string, string>}}
   */
  validateRegister(data) {
    const errors = {};
    const name = (data.name || '').trim();
    const email = (data.email || '').trim();
    const password = data.password || '';
    const confirmPassword = data.confirmPassword || '';

    // Nama: 3 - 50 karakter
    if (!name) {
      errors.name = 'Nama lengkap wajib diisi';
    } else if (name.length < 3 || name.length > 50) {
      errors.name = 'Nama harus antara 3 sampai 50 karakter';
    }

    // Email
    if (!email) {
      errors.email = 'Alamat email wajib diisi';
    } else if (!this.isValidEmail(email)) {
      errors.email = 'Format alamat email tidak valid';
    }

    // Password: 8 - 72 karakter, huruf & angka
    if (!password) {
      errors.password = 'Kata sandi wajib diisi';
    } else if (password.length < 8 || password.length > 72) {
      errors.password = 'Kata sandi harus antara 8 sampai 72 karakter';
    } else if (!/[a-zA-Z]/.test(password) || !/[0-9]/.test(password)) {
      errors.password = 'Kata sandi harus mengandung kombinasi huruf dan angka';
    }

    // Konfirmasi Password
    if (!confirmPassword) {
      errors.confirmPassword = 'Konfirmasi kata sandi wajib diisi';
    } else if (password !== confirmPassword) {
      errors.confirmPassword = 'Konfirmasi kata sandi tidak cocok';
    }

    return {
      isValid: Object.keys(errors).length === 0,
      errors
    };
  },

  /**
   * Validasi form login
   */
  validateLogin(data) {
    const errors = {};
    const email = (data.email || '').trim();
    const password = data.password || '';

    if (!email) {
      errors.email = 'Alamat email wajib diisi';
    } else if (!this.isValidEmail(email)) {
      errors.email = 'Format alamat email tidak valid';
    }

    if (!password) {
      errors.password = 'Kata sandi wajib diisi';
    }

    return {
      isValid: Object.keys(errors).length === 0,
      errors
    };
  },

  /**
   * Validasi form media (tambah / ubah)
   */
  validateMedia(data, isUpdate = false) {
    const errors = {};
    const maxYear = new Date().getFullYear() + 1;

    // Tipe (wajib saat create)
    if (!isUpdate) {
      const type = (data.type || '').trim().toLowerCase();
      if (!['ebook', 'audiobook', 'journal'].includes(type)) {
        errors.type = 'Tipe harus ebook, audiobook, atau journal';
      }
    }

    // Judul: 1 - 150
    const title = (data.title || '').trim();
    if (!title) {
      errors.title = 'Judul wajib diisi';
    } else if (title.length > 150) {
      errors.title = 'Judul maksimal 150 karakter';
    }

    // Penulis: 1 - 100
    const author = (data.author || '').trim();
    if (!author) {
      errors.author = 'Penulis wajib diisi';
    } else if (author.length > 100) {
      errors.author = 'Penulis maksimal 100 karakter';
    }

    // Kategori: 1 - 50
    const category = (data.category || '').trim();
    if (!category) {
      errors.category = 'Kategori wajib diisi';
    } else if (category.length > 50) {
      errors.category = 'Kategori maksimal 50 karakter';
    }

    // Tahun: 1900 - maxYear
    const year = Number(data.year);
    if (!data.year && data.year !== 0) {
      errors.year = 'Tahun wajib diisi';
    } else if (isNaN(year) || year < 1900 || year > maxYear) {
      errors.year = `Tahun harus antara 1900 dan ${maxYear}`;
    }

    // Stok: 0 - 9999
    const stock = Number(data.stock);
    if (data.stock === undefined || data.stock === null || data.stock === '') {
      errors.stock = 'Stok wajib diisi';
    } else if (isNaN(stock) || stock < 0 || stock > 9999 || !Number.isInteger(stock)) {
      errors.stock = 'Stok harus berupa bilangan bulat 0 sampai 9999';
    }

    // Denda: 0 - 1.000.000 (opsional)
    if (data.fee !== undefined && data.fee !== null && data.fee !== '') {
      const fee = Number(data.fee);
      if (isNaN(fee) || fee < 0 || fee > 1000000) {
        errors.fee = 'Denda per hari harus angka 0 sampai 1.000.000';
      }
    }

    // Extra per tipe
    const type = data.type;
    const extra = data.extra || {};

    if (type === 'ebook') {
      const pages = Number(extra.pages);
      if (!extra.pages || isNaN(pages) || pages < 1 || pages > 100000) {
        errors['extra.pages'] = 'Jumlah halaman harus berupa angka 1 sampai 100000';
      }
      const format = (extra.format || '').toUpperCase();
      if (!['PDF', 'EPUB', 'MOBI'].includes(format)) {
        errors['extra.format'] = 'Format harus PDF, EPUB, atau MOBI';
      }
    } else if (type === 'audiobook') {
      const duration = Number(extra.durationMinutes);
      if (!extra.durationMinutes || isNaN(duration) || duration < 1 || duration > 100000) {
        errors['extra.durationMinutes'] = 'Durasi harus berupa angka 1 sampai 100000 menit';
      }
      const narrator = (extra.narrator || '').trim();
      if (!narrator) {
        errors['extra.narrator'] = 'Narator wajib diisi';
      } else if (narrator.length > 100) {
        errors['extra.narrator'] = 'Narator maksimal 100 karakter';
      }
    } else if (type === 'journal') {
      const publisher = (extra.publisher || '').trim();
      if (!publisher) {
        errors['extra.publisher'] = 'Penerbit wajib diisi';
      } else if (publisher.length > 100) {
        errors['extra.publisher'] = 'Penerbit maksimal 100 karakter';
      }
      const volume = (extra.volume || '').trim();
      if (!volume) {
        errors['extra.volume'] = 'Volume wajib diisi';
      } else if (volume.length > 50) {
        errors['extra.volume'] = 'Volume maksimal 50 karakter';
      }
    }

    return {
      isValid: Object.keys(errors).length === 0,
      errors
    };
  },

  /**
   * Menampilkan pesan error ke elemen DOM
   */
  displayErrors(errors, formElement) {
    this.clearErrors(formElement);
    for (const [key, msg] of Object.entries(errors)) {
      // Ubah extra.pages -> extra-pages untuk kecocokan ID form-error
      const safeKey = key.replace('.', '-');
      const errEl = formElement.querySelector(`#err-${safeKey}`);
      if (errEl) {
        errEl.textContent = msg;
      }
      const inputEl = formElement.querySelector(`[name="${key}"], #${safeKey}, [name="extra[${key.split('.')[1]}]"]`);
      if (inputEl) {
        inputEl.classList.add('is-error');
      }
    }
  },

  /**
   * Menghapus pesan error dari form
   */
  clearErrors(formElement) {
    formElement.querySelectorAll('.form-error').forEach(el => el.textContent = '');
    formElement.querySelectorAll('.is-error').forEach(el => el.classList.remove('is-error'));
  }
};
