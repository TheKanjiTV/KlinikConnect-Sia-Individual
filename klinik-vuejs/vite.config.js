import vue from '@vitejs/plugin-vue'
import { defineConfig, loadEnv } from 'vite'

// https://vitejs.dev/config/
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const apiTarget = env.VITE_API_PROXY_TARGET || 'http://localhost:8000'

  return {
    plugins: [vue()],
    server: {
      host: '0.0.0.0',
      proxy: {
        '/api': { target: apiTarget, changeOrigin: true },
        '/sanctum': { target: apiTarget, changeOrigin: true },
        '/storage': { target: apiTarget, changeOrigin: true },
        '/backend': { target: apiTarget, changeOrigin: true },
      },
    },
  }
})


