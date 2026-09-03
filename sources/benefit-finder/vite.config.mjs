import fs from 'node:fs'
import path from 'path'
import { defineConfig, loadEnv } from 'vite'
import react from '@vitejs/plugin-react'
import sassEmbedded from 'sass-embedded'
import eslint from 'vite-plugin-eslint2'
import stylelint from 'vite-plugin-stylelint'
import copy from 'rollup-plugin-copy'
import autoprefixer from 'autoprefixer'
import { distTargets, testConfig, transformers } from './vite-config'

const envLocal = loadEnv('all', process.cwd())
const proxyURL = envLocal.VITE_PROXY_URL || 'http://localhost'
const test = process.env.NODE_ENV === 'test'
const testServer = { port: 6006 }
const devServer = {
  port: 3000,
  open: '/death',
  proxy: {
    '/s3': {
      target: proxyURL,
      changeOrigin: true,
      secure: false,
      ws: true,
    },
  },
}

const copyConfig = test
  ? {}
  : {
      targets: distTargets,
      flatten: false,
      hook: 'writeBundle',
    }

const postcssConfig = {
  plugins: [
    transformers.prependID({
      id: 'benefit-finder',
      ignoreID: '#benefit-finder-modal',
    }),
    autoprefixer(),
  ],
}

const server = test ? testServer : devServer

const INPUT_DIR = './src'
const MODULE_OUTPUT_DIR = path.resolve(
  process.cwd(),
  '../../web/modules/custom/usagov_benefit_finder/modules/usagov_benefit_finder_app/usagov_benefit_finder_page'
)
const OUTPUT_CSS_DIR = path.join(MODULE_OUTPUT_DIR, 'css')

const cleanGeneratedOutput = () => ({
  name: 'clean-generated-output',
  buildStart() {
    fs.rmSync(path.join(MODULE_OUTPUT_DIR, 'assets'), {
      force: true,
      recursive: true,
    })

    if (!fs.existsSync(OUTPUT_CSS_DIR)) {
      return
    }

    for (const fileName of fs.readdirSync(OUTPUT_CSS_DIR)) {
      if (/^benefit-finder\d+\.min\.css$/.test(fileName)) {
        fs.rmSync(path.join(OUTPUT_CSS_DIR, fileName), { force: true })
      }
    }
  },
})

const aliasConfig = {
  '@': path.resolve(INPUT_DIR),
  '@api': path.resolve(INPUT_DIR, './shared/api'),
  '@routes': path.resolve(INPUT_DIR, './Routes'),
  '@components': path.resolve(INPUT_DIR, './shared/components'),
  '@hooks': path.resolve(INPUT_DIR, './shared/hooks'),
  '@locales': path.resolve(INPUT_DIR, './shared/locales'),
  '@styles': path.resolve(INPUT_DIR, './shared/styles'),
  '@utils': path.resolve(INPUT_DIR, './shared/utils'),
}

// https://vitejs.dev/config/
export default defineConfig({
  base: './',
  plugins: [
    cleanGeneratedOutput(),
    react(),
    eslint({ cache: true, lintOnStart: true, lintInWorker: true }),
    stylelint({ cache: true, lintOnStart: true, lintInWorker: true }),
    copy(copyConfig),
  ],
  resolve: {
    alias: aliasConfig,
  },
  optimizeDeps: {
    exclude: ['@storybook/addon-docs'],
    include: ['jsdoc-type-pratt-parser'],
  },
  css: {
    postcss: postcssConfig,
    preprocessorOptions: {
      scss: {
        api: 'modern-compiler',
        implementation: sassEmbedded,
      },
    },
  },
  server: { ...server },
  test: testConfig,
  build: {
    emptyOutDir: false,
    outDir: MODULE_OUTPUT_DIR,
    chunkSizeWarningLimit: 1000,
    rollupOptions: {
      output: {
        entryFileNames: `js/benefit-finder.min.js`,
        assetFileNames: assetInfo => {
          if (assetInfo.names?.some(n => n.endsWith('.css'))) {
            return 'css/benefit-finder.min.css'
          }
          return 'assets/[name].[ext]'
        },
      },
    },
  },
})
