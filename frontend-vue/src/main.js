import { createApp } from 'vue'
import './style.css'
import App from './App.vue'
import router from './router.js'
import './utils/darkMode'
// import './index.css'

const app = createApp(App)
app.use(router)
app.mount('#app')

// Load real-time (Echo) after first paint so initial route loads faster
const loadRealtime = () => import('./bootstrap')
if (typeof window !== 'undefined' && 'requestIdleCallback' in window) {
  window.requestIdleCallback(() => loadRealtime(), { timeout: 2000 })
} else {
  setTimeout(loadRealtime, 0)
}
