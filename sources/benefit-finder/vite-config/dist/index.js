const distTargets = [
  {
    src: ['src /*', '!src/setupTests.js'],
    dest: 'dist',
    expandDirectories: true,
    onlyFiles: true,
  },
  {
    src: ['package.json', 'package-lock.json'],
    dest: 'dist',
    expandDirectories: true,
    onlyFiles: true,
  },
  {
    src: ['src/App/*', '!src/App/*.stories.jsx'],
    dest: 'dist/src',
    expandDirectories: true,
    onlyFiles: true,
  },
  {
    src: 'src/shared/api/*',
    dest: 'dist/src/',
    expandDirectories: true,
    onlyFiles: true,
  },
  {
    src: 'src/shared/locales',
    dest: 'dist/src/',
  },
  {
    src: [
      'src/shared/hooks/*',
      'src/shared/hooks/**/*',
      '!src/shared/hooks/**/**/__tests__',
    ],
    dest: 'dist/src/',
    expandDirectories: true,
    onlyFiles: true,
  },
  {
    src: 'src/shared/styles',
    dest: 'dist/src/',
  },
  {
    src: [
      'src/shared/utils/*',
      'src/shared/utils/**/*',
      '!src/shared/utils/**/**/__tests__',
    ],
    dest: 'dist/src/',
    expandDirectories: true,
    onlyFiles: true,
  },
  {
    src: [
      'src/shared/components/*',
      'src/shared/components/**/*',
      '!src/shared/components/**/**/__tests__',
      '!src/shared/components/**/index.stories.jsx',
    ],
    dest: 'dist/src/',
    expandDirectories: true,
    onlyFiles: true,
  },
]

export default distTargets
