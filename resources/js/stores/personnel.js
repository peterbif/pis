import { defineStore } from 'pinia';
import axios from 'axios';

axios.defaults.withCredentials = true;
axios.defaults.withXSRFToken = true;

export const usePersonnelStore = defineStore('personnel', {
  state: () => ({ user: null, lookups: {}, sessionLoading: true }),
  actions: {
    async csrf() { await axios.get('/sanctum/csrf-cookie'); },
    async restoreSession() {
      try { const r = await axios.get('/api/user'); this.user = r.data; }
      catch { this.user = null; }
      finally { this.sessionLoading = false; }
    },
    async loadLookups() { try { this.lookups = (await axios.get('/api/lookups')).data; } catch { this.lookups = {}; } },
    async checkEmail(email) { await this.csrf(); return (await axios.post('/api/signup/check-email', { email })).data; },
    async beginSignup(payload) { await this.csrf(); return (await axios.post('/api/signup', payload)).data; },
    async verifyOtp(payload) { await this.csrf(); return (await axios.post('/api/signup/verify', payload)).data; },
    async login(payload) { await this.csrf(); const r = await axios.post('/api/login', payload); this.user = r.data.user; return r.data; },
    async logout() { try { await this.csrf(); await axios.post('/api/logout'); } finally { this.user = null; } },
    async saveProfile(payload) { return (await axios.put('/api/personnel/profile', payload)).data; },
    async clearProfileSection(section) { return (await axios.put('/api/personnel/profile', { clear_section: section })).data; },
    async fetchProfile() { return (await axios.get('/api/personnel/profile')).data; },
  },
});