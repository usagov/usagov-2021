import js from '@eslint/js'
import globals from 'globals'
import json from 'eslint-plugin-json'
import pluginCypress from 'eslint-plugin-cypress/flat'
import storybook from 'eslint-plugin-storybook'
import reactPlugin from 'eslint-plugin-react'
import eslintConfigPrettier from 'eslint-config-prettier'
import eslintPluginPrettierRecommended from 'eslint-plugin-prettier/recommended'
import jsxA11y from 'eslint-plugin-jsx-a11y'

// error when starting dev server:
// Error: Key "languageOptions": Key "globals": Global "AudioWorkletGlobalScope " has leading or trailing whitespace.
const GLOBALS_BROWSER_FIX = Object.assign({}, globals.browser, {
  AudioWorkletGlobalScope: globals.browser['AudioWorkletGlobalScope '],
})

delete GLOBALS_BROWSER_FIX['AudioWorkletGlobalScope ']

export default [
  js.configs.recommended,
  json.configs.recommended,
  eslintConfigPrettier,
  eslintPluginPrettierRecommended,
  ...storybook.configs['flat/recommended'],
  pluginCypress.configs.recommended,
  reactPlugin.configs.flat.all,
  jsxA11y.flatConfigs.recommended,
  {
    files: ['**/*.js', '**/*.mjs'],
    languageOptions: {
      parserOptions: {
        ecmaVersion: 'latest',
        sourceType: 'module',
      },
      globals: {
        ...globals.node,
        ...GLOBALS_BROWSER_FIX,
      },
    },
  },
  {
    files: ['**/*.spec.js', '**/*.spec.jsx'],
    languageOptions: {
      parserOptions: {
        ecmaVersion: 'latest',
        sourceType: 'module',
      },
      globals: {
        ...globals.node,
        ...globals.jest,
      },
    },
  },
  {
    files: ['**/*.jsx'],
    ...reactPlugin.configs.flat.recommended,
    settings: {
      react: {
        version: 'detect',
      },
    },
    languageOptions: {
      ...reactPlugin.configs.flat.recommended.languageOptions,
      parserOptions: {
        ecmaVersion: 'latest',
        sourceType: 'module',
      },
      globals: {
        ...globals.node,
        ...GLOBALS_BROWSER_FIX,
      },
    },
    rules: {
      'react/jsx-indent': 'off',
      'react/jsx-indent-props': 'off',
      'react/jsx-newline': 'off',
      'react/jsx-one-expression-per-line': 'off',
      'react/jsx-max-props-per-line': 'off',
      'react/jsx-no-bind': 'off',
      'react/prop-types': 'off',
      'react/function-component-definition': 'off',
      'react/react-in-jsx-scope': 'off',
      'react/jsx-sort-props': 'off',
      'react/button-has-type': 'off',
      'react/jsx-no-literals': 'off',
      'react/jsx-boolean-value': 'off',
      'react/jsx-closing-tag-location': 'off',
      'react/self-closing-comp': 'off',
      'react/forbid-component-props': 'off',
      'react/require-default-props': 'off',
      'react/sort-prop-types': 'off',
      'react/jsx-props-no-spreading': 'off',
      'react/no-multi-comp': 'off',
      'react/no-unstable-nested-components': 'off',
      'react/no-unused-prop-types': 'off',
      'react/forbid-prop-types': 'off',
      'react/jsx-max-depth': 'off',
      'react/jsx-no-leaked-render': 'off',
      'react/no-array-index-key': 'off',
      'react/jsx-no-useless-fragment': 'off',
      'react/no-danger': 'off',
      'react/hook-use-state': 'off',
      'react/jsx-curly-newline': 'off',
      'react/jsx-curly-brace-presence': 'off',
    },
  },
  {
    files: ['**/*.jsx'],
    ...jsxA11y.flatConfigs.recommended,
    languageOptions: {
      ...jsxA11y.flatConfigs.recommended.languageOptions,
      globals: {
        ...GLOBALS_BROWSER_FIX,
      },
    },
    rules: {
      'jsx-a11y/no-noninteractive-tabindex': 'off',
      'jsx-a11y/anchor-is-valid': 'off',
      'jsx-a11y/mouse-events-have-key-events': 'off',
    },
  },
  {
    ignores: [
      'dist',
      'build',
      'node_modules',
      'themes',
      'coverage',
      'storybook-static',
      'package-lock.json',
      '!.storybook',
    ],
  },
]
