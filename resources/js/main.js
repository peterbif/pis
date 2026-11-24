import { createApp } from 'vue';
import { createPinia } from 'pinia';
import { createVuetify } from 'vuetify';
import { aliases, mdi } from 'vuetify/iconsets/mdi';
import 'vuetify/styles';
import '@mdi/font/css/materialdesignicons.css';
import 'sweetalert2/dist/sweetalert2.min.css';
import './style.css';
import './echo.js';
import App from './App.vue';
import { usePersonnelStore } from './stores/personnel';

const vuetify = createVuetify({
  icons: { defaultSet: 'mdi', aliases, sets: { mdi } },
  theme: { defaultTheme: 'people', themes: { people: { colors: { primary: '#087e4b', secondary: '#b7d8c4', surface: '#ffffff', background: '#f3f8f4', error: '#b7473c' } } } },
  defaults: { VBtn: { rounded: 'lg', elevation: 0 }, VCard: { rounded: 'xl', elevation: 0 }, VTextField: { variant: 'outlined', density: 'comfortable', hideDetails: 'auto' }, VSelect: { variant: 'outlined', density: 'comfortable', hideDetails: 'auto' } },
});

const pinia = createPinia();
const app = createApp(App).use(pinia).use(vuetify);
async function startApp() {
  await usePersonnelStore(pinia).restoreSession();
  app.mount('#app');
}
startApp();
