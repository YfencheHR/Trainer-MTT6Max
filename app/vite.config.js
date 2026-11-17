import { defineConfig } from 'vite'

// El layout (resources/views/app.blade.php) carga /assets/app.js y /assets/app.css
// con nombres fijos (sin hash), por eso el build sale directo a public/assets.
// Las fuentes y fonts.css ya viven en public/assets y no se tocan (emptyOutDir: false).
export default defineConfig({
  publicDir: false,
  build: {
    outDir: 'public/assets',
    emptyOutDir: false,
    rollupOptions: {
      input: {
        app: 'resources/js/app.js',
        'app-css': 'resources/css/app.css',
      },
      output: {
        entryFileNames: '[name].js',
        chunkFileNames: '[name].js',
        assetFileNames: (info) =>
          info.names?.some((n) => n.endsWith('.css')) ? 'app.css' : '[name][extname]',
      },
    },
  },
})
