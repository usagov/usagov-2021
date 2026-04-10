# Benefit Finder React Application

```text
/sources/benefit-finder
  |-.husky
    |-git-hooks
  |-.storybook
  |-cypress
  |-nginx
  |-src
    |-App
    |-Routes
    |-shared
      |-api
      |-components
        |-ComponentName
          |-__tests__
            |-__snapshots__
            index.spec.js
            indexComponentName.spec.cy.js
          _index.scss
          index.jsx
          index.stories.jsx
      |-hooks
      |-locales
      |-style-docs
      |-styles
      |-utils
    |-index.jsx
    |-setupTest.js
  |-vite-config
  |-README.md
```

<br>

### Basics:

- "Modules", like `App` should follow the structure of routes.
- Files from one module can only import from ancestor folders within the same module or from `src/components/shared`.

<br>

| File or folder          | Description                                                |
| ----------------------- | ---------------------------------------------------------- |
| `src/index.jsx`         | Entry file.                                                |
| `index.html`            | All scripts and styles injected here                       |
| `src/App`               | Main application routes, global / ancestor of all modules. |
| `src/Routes`            | Route components                                           |
| `src/shared/api`        | dev utils for interacting with data                        |
| `src/shared/components` | Components, constants, machines, hooks, styles, utils etc; |
| `src/shared/hooks`      | Custom Hooks                                               |
| `src/shared/style-docs` | mdx files for !stories documentation                       |
| `src/shared/styles`     | scss partials                                              |
| `src/shared/utils`      | custom Javascript utilities                                |
| `vite-config.mjs`       | vite bundler config, imports from `vite-config` dir        |
|                         | **_Any module is allowed to import from shared._**         |

<br>

### File Types:

| file type       | Description                                              |
| --------------- | -------------------------------------------------------- |
| `.jsx`          | React functional components                              |
| `.js`           | ES                                                       |
| `.stories.jsx`  | Component Story Format files for storybook stories       |
| `.mdx`          | Markdown files that accept ES imports for storybook docs |
| `.md`           | Markdown files for project docs                          |
| `.mjs`          | ES Module File                                           |
| `.spec.js`      | Testing specification                                    |
| `.spec.cy.js`   | Cypress testing specification                            |
| `.spec.js.snap` | Testing snapshots                                        |
| `.hbs`          | Handlebars (used in plop generated components)           |
| `.scss`         | Syntactically awesome style sheets                       |
| `.json`         | JavaScript Object Notation                               |
| `*rc`           | Resource files                                           |
| `*sh`           | Bash files                                               |

<br>

## Getting Started

### Get our packages.

```shell
npm install
```

### Optional `.env.local` override

The Vite dev server proxies Benefit Finder JSON requests to local Drupal at
`http://localhost` by default. If your Drupal site is running somewhere else,
create a `.env.local` file to override that target.

1. Copy environment example file

```shell
cp .env.local.example .env.local
```

2. Replace the value with your Drupal base URL as needed

### Build and serve development environment.

There are three local build environments, one for our component workshop (Storybook), another for our Application (Vite), and finally a test e2e test env (Cypress).

The helper script `../../scripts/benefit-finder/sync-usagov-theme.sh` copies the
current Drupal theme into the Benefit Finder workspace so Vite and Storybook can
use those assets locally. You do not usually need to run it yourself:

- `npm start` runs it automatically through `prestart`
- Storybook commands run it automatically through `prebuild:storybook`

Run it directly only if you need to refresh the copied theme assets without
starting one of those commands.

#### Storybook

Run development workshop

```shell
npm run dev:storybook
```

#### Vite App

Run development server from `vite.config.mjs`

```shell
npm start
```

`npm run dev` works too.

If you change files in `web/themes/custom/usagov` and want those copied into the
Benefit Finder workspace immediately, run:

```shell
npm run sync:usagov:theme
```

#### Module asset build

The compiled module assets are committed in this repo. To rebuild the files that
Drupal serves, run the repo-level helper from the repository root:

```shell
./scripts/benefit-finder/build-module-assets.sh
```

To rebuild automatically while you work:

```shell
./scripts/benefit-finder/watch-module-assets.sh
```

#### Storybook

Run e2e development config

```shell
npm cy:dev:storybook
```

## Git Hooks

We use husky to manage git hooks.

husky is automatically installed after the packages are installed.

- The `pre-commit` git hook leverages `lint-staged` to run a series of scripts on any staged files when a commit is made.

- The `pre-push` git hook runs a series of scripts on the directory before pushing to the origin.

## Coding Standards

We use a combination of linting and best practices to guide code consistency.

<br>

### Our linters include

[![js-standard-style](https://img.shields.io/badge/code%20style%20JS-standard-brightgreen.svg)](http://standardjs.com)
[![react/recommended](https://img.shields.io/badge/code%20style%20React-recommended-brightgreen.svg)](https://github.com/yannickcr/eslint-plugin-react)
[![prettier/recommended](https://img.shields.io/badge/code%20format/prettier-%20recomended-brightgreen.svg)](https://github.com/prettier/eslint-plugin-prettier)
[![stylelint](https://img.shields.io/badge/code%20format/stylelint-%20standard-brightgreen.svg)](https://github.com/cypress-io/eslint-plugin-cypress)

<br>

#### Linting and Formatting Scripts

We include a few scripts in `package.json` that can be executed to interact with our standards.

Run eslint on `.js` and `.jsx` files

```shell
npm run lint:js
```

Auto-fix lint errors

```shell
npm run lint:js:fix
```

Format `.js` and `.jsx` files with prettier.

```shell
npm run format
```

Run stylelint on `.scss` files

```shell
npm run lint:scss
```

Auto-fix lint errors

```shell
npm run lint:scss:fix
```

<br>

### **Standards outside linting**

We also carry the following working agreements that may fall outside of the linting.

---

<br>

#### **1. Avoid functionality in JSX**

If you have this...

```jsx
<Foo onClick={​​​​​​​​function () {​​​​​​​​ alert("1337") }​​​​​​​​}​​​​​​​​ />
```

We prefer this

```jsx
<Foo onClick={​​​​​​​​handleAlert}​​​​​​​​ />
```

<br>

#### **2. Avoid inline styles**

If you have this...

```jsx
<Foo style={{ width: 100% }} />
```

We prefer this

```jsx
<Foo class="full-width" />
```

<br>

#### **3. PropTypes**

Typecheck with [PropTypes](https://reactjs.org/docs/typechecking-with-proptypes.html)

<br>

#### **4. Styles**

We take a utility first approach. We do not have full control over our styles since a custom version of USWDS already exist in the usa.gov project.

1. we establish uswds components with `usa-<class>` classes.
2. this inherits global `uswds` `css` and `js`
3. IF we need to override, we clone the uswds class `usa-` and prepend `bf-`.

```css
.bf-usa-<class> .usa-<class>
```

4. custom classes do not include `usa-` but still include `bf-`

```css
.bf-<class>
```

We use [Sass](https://sass-lang.com/) and [Sass Modules](https://css-tricks.com/introducing-sass-modules/)

Generally, it is recommend that you don’t reuse the same CSS classes across different components. For example, instead of using a `.usa-button` CSS class in `<AcceptButton>` and `<RejectButton>` components, it is recommended to create a `<Button>` component with its own `.usa-button` styles, that both `<AcceptButton>` and `<RejectButton>` can render (but not inherit).

We use composition, but leverage the https://designsystem.digital.gov/. We will attempt to use pre-processor methods when it makes sense to do so.

<br>

#### **5. Code Documentation**

We use inline documentation for functions, hooks, and functional components.

Example with function

```js
/**
 * a boolean function that returns a component when true.
 * @function
 * @param {boolean} value - the inherited value, can be true || false
 * @return {component} returns a component if true
 */
const handleTruth = value => value === true && <Truth />
```

Example with hook

```js
/**
 * a boolean that manages the state of truth.
 * @function
 * @param {boolean} value - the inherited value, can be true || false
 * @return {state} returns an updated state for value
 */
const [value, setValue] = useState(true)
```

Example with functional component

```js
/**
 * a functional component that wraps form elements in a form element
 * @component
 * @param {node} children - inherited children
 * @return {html} returns a semantic html div element, with all its children
 */
function Value = ({ children }) => (<div className="wrapper">{children}</div>)
```

<br>

#### **5. Testing**

##### Vitest for unit/DOM testing

We use [Vitest](https://vitest.dev/) for Unit/DOM testing

```sh
npm run test
```

##### Cypress for UI E2E Test

```bash
npm run cy:dev:storybook
```

**Description:**
Starts Storybook for the Cypress suite at `http://localhost:6006`.

In a second terminal, run:

```bash
npm run cy:run:e2e
```

**Description:**
Runs the Storybook E2E suite in the test environment using headless Chrome. Storybook must already be running.

```bash
npm run cy:run:prod:links:e2e
```

**Description:**
Runs the production-links Cypress configuration in Chrome. This command expects a `cypress/e2e/usagov-public-site/links.cy.js` spec file to exist.

```bash
npm run cy:build:storybook
npm run cy:run:pipeline
```

**Description:**
This is the CI helper flow. `cy:run:pipeline` serves `storybook-static/` on port `6006` and does not launch Cypress by itself.

See `cypress/README.md` in this directory for the full local and CI Cypress workflow.

For more information, refer to the [Cypress Documentation](https://docs.cypress.io).

<br>

#### **6. Components**

We build each of our components with `spec`, `scss`, `stories` and `jsx` files

```
|-ComponentName
  |-__tests__
    |-__snapshots__
    index.spec.js
  _index.scss
  index.jsx
  index.stories.jsx
```

To generate a component with these files based on a name space,
you can use [plop](https://github.com/plopjs/plop)

`cd` to the root of the application

```sh
npm run generate:component <component-name>
```

> It's important to export components from the root of the shared index file. This is where you will import and destructure across other documents.
