const testConfig = {
  root: 'src',
  environment: 'jsdom',
  globals: true,
  setupFiles: 'setupTests.js',
  moduleNameMapper: {
    '@uswds/uswds/js/usa-accordion':
      '<rootDir>/node_modules/@uswds/uswds/packages/usa-accordion/src/index',
    '@uswds/uswds/js/usa-modal':
      '<rootDir>/node_modules/@uswds/uswds/packages/usa-modal/src/index',
  },
  include: ['**/?(*.)+(spec|test).[jt]s?(x)'],
  exclude: ['**/__tests__/assets/*.spec.jsx'],
  coverage: {
    reportsDirectory: '../coverage',
    reporter: ['text', 'json'],
    thresholds: {
      branches: 75,
      functions: 55,
      lines: 80,
      statements: 80,
    },
    include: ['**/*.{js,jsx}'],
    exclude: [
      'node_modules/',
      'setupTests.ts',
      'jest-test-results.json',
      '**/*.stories.jsx',
      '**/*.cy.jsx',
      '<rootDir>/node_modules/',
      '**/hooks/index.js',
      '**/components/index.js',
      'reportWebVitals.js',
      'index.jsx',
      '**/assets/*',
      '**/utils/index.js',
    ],
  },
}

export default testConfig
