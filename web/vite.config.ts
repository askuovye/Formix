import { fileURLToPath, URL } from 'node:url'

import { defineConfig, type Plugin } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'

/**
 * Pré-carrega a Inter 400 (texto) e 700 (títulos) no build: sem isso, a fonte
 * só começa a baixar depois do CSS, e o título da landing troca de fonte na tela.
 * Os nomes têm hash, por isso o link é gerado a partir do bundle.
 */
function preloadFonts(): Plugin {
  return {
    name: 'formix:preload-fonts',
    apply: 'build',
    transformIndexHtml: {
      order: 'post',
      handler: (_html, { bundle }) =>
        Object.keys(bundle ?? {})
          .filter((file) => /inter-latin-(400|700)-[\w-]+\.woff2$/.test(file))
          .map((file) => ({
            tag: 'link',
            attrs: {
              rel: 'preload',
              as: 'font',
              type: 'font/woff2',
              href: `/${file}`,
              crossorigin: '',
            },
            injectTo: 'head' as const,
          })),
    },
  }
}

// https://vite.dev/config/
export default defineConfig({
  plugins: [vue(), vueDevTools(), preloadFonts()],
  css: {
    preprocessorOptions: {
      scss: {
        // Disponibiliza mixins e breakpoints em todo <style lang="scss">.
        additionalData: `@use "@/styles/mixins" as *;\n`,
      },
    },
  },
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
      '@fixtures': fileURLToPath(new URL('../fixtures', import.meta.url)),
    },
  },
})
