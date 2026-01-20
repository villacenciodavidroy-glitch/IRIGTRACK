import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// https://vite.dev/config/
export default defineConfig({
  plugins: [vue()],
  base: '/',
  server: {
    port: 5174,
    strictPort: true,
    host: true, // Listen on all addresses
    hmr: {
      host: 'localhost',
      port: 5174,
      protocol: 'ws'
    },
    watch: {
      usePolling: false
    },
    proxy: {
      '/api': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: true,
        secure: false
      }
    }
  }
})
