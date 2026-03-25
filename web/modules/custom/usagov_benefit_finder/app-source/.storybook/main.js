import { mergeConfig } from 'vite'
import { fileURLToPath } from 'url'

const config = {
  staticDirs: ['../themes'],
  stories: ['../src/**/*.mdx', '../src/**/*.stories.@(js|jsx)'],
  addons: [
    '@storybook/addon-a11y',
    '@storybook/addon-links',
    '@storybook/addon-docs',
  ],
  docs: {
    autodocs: 'tag',
  },
  framework: '@storybook/react-vite',
  // Explicitly register the React renderer preview annotations because the
  // @storybook/react-vite preset's core.renderer (a file:// URL) is not being
  // resolved correctly by Storybook's safeResolveModule on this platform.
  async previewAnnotations(input, options) {
    const docsConfig = await options.presets.apply('docs', {}, options)
    const docsEnabled = Object.keys(docsConfig).length > 0
    return [
      ...input,
      fileURLToPath(import.meta.resolve('@storybook/react/entry-preview')),
      fileURLToPath(import.meta.resolve('@storybook/react/entry-preview-argtypes')),
      ...(docsEnabled
        ? [fileURLToPath(import.meta.resolve('@storybook/react/entry-preview-docs'))]
        : []),
    ]
  },
  viteFinal(config) {
    return mergeConfig(config, {
      build: {
        chunkSizeWarningLimit: 1000,
        rollupOptions: {
          output: {
            manualChunks: id =>
              id.includes('src/App/index.jsx') ? 'app-chunk' : undefined,
          },
        },
      },
    })
  },
}

export default config
