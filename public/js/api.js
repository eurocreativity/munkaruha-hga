const API = {
  getToken() {
    return localStorage.getItem('hga_token');
  },

  setToken(token) {
    if (token) localStorage.setItem('hga_token', token);
    else localStorage.removeItem('hga_token');
  },

  getUser() {
    const u = localStorage.getItem('hga_user');
    return u ? JSON.parse(u) : null;
  },

  setUser(user) {
    if (user) localStorage.setItem('hga_user', JSON.stringify(user));
    else localStorage.removeItem('hga_user');
  },

  async request(endpoint, options = {}) {
    const headers = options.headers || {};
    const token = this.getToken();

    if (token) {
      headers['Authorization'] = `Bearer ${token}`;
    }

    if (!(options.body instanceof FormData)) {
      headers['Content-Type'] = 'application/json';
    }

    const config = {
      ...options,
      headers
    };

    try {
      const response = await fetch(`/api${endpoint}`, config);
      if (response.status === 401 || response.status === 403) {
        if (endpoint !== '/auth/login') {
          this.setToken(null);
          this.setUser(null);
          window.location.reload();
          return;
        }
      }

      const data = await response.json();
      if (!response.ok) {
        throw new Error(data.error || data.message || 'Hiba történt a művelet során');
      }
      return data;
    } catch (err) {
      throw err;
    }
  },

  // Auth
  login(username, password) {
    return this.request('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ username, password })
    });
  },

  getMe() {
    return this.request('/auth/me');
  },

  getUsers() {
    return this.request('/auth/users');
  },

  createUser(userData) {
    return this.request('/auth/users', {
      method: 'POST',
      body: JSON.stringify(userData)
    });
  },

  updateUser(id, userData) {
    return this.request(`/auth/users/${id}`, {
      method: 'PUT',
      body: JSON.stringify(userData)
    });
  },

  // Telephelyek
  getLocations() {
    return this.request('/locations');
  },

  // Dolgozók
  getEmployees(params = {}) {
    const q = new URLSearchParams(params).toString();
    return this.request(`/employees${q ? '?' + q : ''}`);
  },

  getEmployee(id) {
    return this.request(`/employees/${id}`);
  },

  createEmployee(empData) {
    return this.request('/employees', {
      method: 'POST',
      body: JSON.stringify(empData)
    });
  },

  updateEmployee(id, empData) {
    return this.request(`/employees/${id}`, {
      method: 'PUT',
      body: JSON.stringify(empData)
    });
  },

  // Ruhák
  getClothes(params = {}) {
    const q = new URLSearchParams(params).toString();
    return this.request(`/clothes${q ? '?' + q : ''}`);
  },

  getClothByBarcode(barcode) {
    return this.request(`/clothes/by-barcode/${encodeURIComponent(barcode)}`);
  },

  createCloth(clothData) {
    return this.request('/clothes', {
      method: 'POST',
      body: JSON.stringify(clothData)
    });
  },

  updateCloth(id, clothData) {
    return this.request(`/clothes/${id}`, {
      method: 'PUT',
      body: JSON.stringify(clothData)
    });
  },

  // Mosoda
  scanLaundry(data) {
    return this.request('/laundry/scan', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  },

  finishBatch(batchId, notes) {
    return this.request('/laundry/batch/finish', {
      method: 'POST',
      body: JSON.stringify({ batch_id: batchId, notes })
    });
  },

  createBatch(data) {
    return this.request('/laundry/batch/create', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  },

  getBatches(params = {}) {
    const q = new URLSearchParams(params).toString();
    return this.request(`/laundry/batches${q ? '?' + q : ''}`);
  },

  getBatchDetails(id) {
    return this.request(`/laundry/batch/${id}`);
  },

  getInLaundry(params = {}) {
    const q = new URLSearchParams(params).toString();
    return this.request(`/laundry/in-laundry${q ? '?' + q : ''}`);
  },

  // Statisztikák & Leltár
  getStats(params = {}) {
    const q = new URLSearchParams(params).toString();
    return this.request(`/inventory/stats${q ? '?' + q : ''}`);
  },

  importCsv(formData) {
    return this.request('/inventory/import-csv', {
      method: 'POST',
      body: formData
    });
  },

  // Audit
  getAuditLogs(params = {}) {
    const q = new URLSearchParams(params).toString();
    return this.request(`/audit${q ? '?' + q : ''}`);
  }
};

window.API = API;
