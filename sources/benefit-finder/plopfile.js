module.exports = plop => {
  plop.setGenerator('component', {
    description: 'Create a reusable component',
    prompts: [
      {
        type: 'input',
        name: 'name',
        message: 'What is your component name?',
      },
    ],
    actions: [
      {
        type: 'add',
        // Plop will create directories for us if they do not exist
        // so it's okay to add files in nested locations.
        path: 'src/shared/components/{{pascalCase name}}/index.jsx',
        templateFile:
          'src/shared/components/.ComponentTemplate/Component.jsx.hbs',
      },
      {
        type: 'add',
        path: 'src/shared/components/{{pascalCase name}}/__tests__/index.spec.jsx',
        templateFile:
          'src/shared/components/.ComponentTemplate/Component.spec.jsx.hbs',
      },
      {
        type: 'add',
        path: 'src/shared/components/{{pascalCase name}}/_index.scss',
        templateFile:
          'src/shared/components/.ComponentTemplate/_Component.scss.hbs',
      },
      {
        type: 'add',
        path: 'src/shared/components/{{pascalCase name}}/index.stories.jsx',
        templateFile:
          'src/shared/components/.ComponentTemplate/Component.stories.jsx.hbs',
      },
    ],
  })
}
