import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import process from 'node:process'

// The browser calls /api on the same origin; Vite forwards it to Laravel.
// Locally Laravel runs on 127.0.0.1:8000; in Docker API_TARGET points at the backend container.
export default defineConfig({
  plugins: [react()],
  server: {
    host: true,
    port: 5173,
    proxy: {
      '/api': process.env.API_TARGET || 'http://127.0.0.1:8000',
    },
  },
})
