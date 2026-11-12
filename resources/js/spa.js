import { createApp } from 'vue';
import SpaApp from './spa/SpaApp.vue';
import router from './spa/router.js';
import '../css/app.css';
import '../css/dashboard.css';
import '../css/transactions/leasing.css';
import '../css/transactions/jual.css';
import '../css/spa.css';

createApp(SpaApp).use(router).mount('#app');
