import { mergeConfig } from 'vite'

const config = {
  staticDirs: ['../themes'],
  stories: ['../src/**/*.mdx', '../src/**/*.stories.@(js|jsx)'],
  addons: [
    '@storybook/addon-a11y',
    '@storybook/addon-links',
    '@storybook/addon-essentials',
    '@storybook/addon-interactions',
    '@storybook/addon-mdx-gfm',
  ],
  docs: {
    autodocs: 'tag',
  },
  framework: '@storybook/react-vite',
  core: {
    builder: '@storybook/builder-vite',
  },
  viteFinal(config) {
    return mergeConfig(config, {
      build: {
        chunkSizeWarningLimit: '1000',
        rollupOptions: {
          output: {
            manualChunks: id =>
              id.includes('src/App/index.jsx') ? 'app-chunk' : false,
          },
        },
      },
    })
  },
}

export default config
