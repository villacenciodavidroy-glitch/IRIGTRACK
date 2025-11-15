import { createApp } from 'vue'
import './style.css'
import App from './App.vue'
import router from './router.js'
import './utils/darkMode'
import './bootstrap' // Initialize Laravel Echo for real-time updates
// import './index.css'

const app = createApp(App)
app.use(router)
app.mount('#app')
