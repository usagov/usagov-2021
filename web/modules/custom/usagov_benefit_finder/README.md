
# Benefit Finder v2 Custom Drupal Module

## Structure

```text
/usagov_benefit_finder
  |-app-source                    ← React application source code
    |-src                          React components and logic
    |-package.json                 Dependencies (security-fixed)
    |-package-lock.json            Locked dependency versions
    |-vite.config.mjs              Build configuration
  |-config
  |-modules
    |-usagov_benefit_finder_api
      |-src
        |-Controller
          LifeEventController.php
      usagov_benefit_finder_api.module
    |-usagov_benefit_finder_app
      |-usagov_benefit_finder_page
        |-css
          benefit-finder.min.css   ← Built by app-source
        |-js
          benefit-finder.min.js    ← Built by app-source
        |-templates
          page--benefit-finder-life-event.html.twig
        usagov_benefit_finder_page.libraries.yml
        usagov_benefit_finder_page.module
    |-usagov_benefit_finder_content
      |-src
      usagov_benefit_finder_content.module
  |-src
    |-Form
      BenefitFinderSettingsForm.php
    |-Traits
      BenefitFinderTrait.php
  |-tests
  |-README.md
```

## Basics

| File or folder                              | Description                                                        |
|---------------------------------------------|--------------------------------------------------------------------|
| `app-source/`                               | **React application source code (built during Docker build)**      |
| `usagov_benefit_finder_api`                 | Benefit finder API module                                          |
| `LifeEventController.php`                   | Process benefit finder content to generate JSON data and JSON file |
| `usagov_benefit_finder_api.module`          | JSON file generation batch job                                     |
| `usagov_benefit_finder_page`                | Benefit finder page module                                         |
| `benefit-finder.min.css`                    | Benefit finder app css (generated from app-source)                 |
| `benefit-finder.min.js`                     | Benefit finder app JavaScript (generated from app-source)          |
| `page--benefit-finder-life-event.html.twig` | Benefit finder page template                                       |
| `usagov_benefit_finder_page.libraries.yml`  | Benefit finder app library                                         |
| `usagov_benefit_finder_page.module`         | Benefit finder page theme, preprocess, attach library              |
| `usagov_benefit_finder_content`             | Benefit finder content module                                      |
| `usagov_benefit_finder_content.module`      | Provide benefit finder content form validation                     |
| `src/Form/BenefitFinderSettingsForm.php`    | Form to set up automate JSON data file generation                  |
| `src/Traits/BenefitFinderTrait.php`         | Functions to get benefit finder node                               |

## React Application (app-source/)

The benefit finder React application source code lives in the `app-source/` directory within this module. 
It is built during the Docker build process and outputs minified assets to the module's library directory.

### Building the React App

**Automatic build (via Docker):**
The React app is automatically built when you run `bin/build` or during CircleCI deployment. 
The Dockerfile includes a `benefit-finder-builder` stage that:
1. Installs dependencies from package-lock.json
2. Runs `npm run build`
3. Outputs `benefit-finder.min.js` and `benefit-finder.min.css` to the module library

**Manual local development:**
```bash
cd web/modules/custom/usagov_benefit_finder/app-source

# First time: install dependencies
npm ci

# Development mode with hot reload
npm start

# Run tests
npm test

# Build for production (outputs to ../modules/usagov_benefit_finder_app/usagov_benefit_finder_page/)
npm run build
```

### React App Technologies
- **React 18.3.1** - UI framework
- **React Router 7.1.4** - Client-side routing
- **Vite** - Build tool and bundler
- **Vitest** - Testing framework
- **USWDS** - US Web Design System styles

### Security
All dependencies have been updated to fix known vulnerabilities. The `package-lock.json` file 
locks dependency versions to ensure consistent, secure builds.

## Benefit finder API module

The `usagov_benefit_finder_api` custom Drupal module works on reading and processing benefit finder content,
generating JSON data and JSON file for a given life event; creating and running batch job to update JSON
files of all life events when content editors add/update benefit finder content.

### Life event controller

The `LifeEventController.php` life event controller has the following functions:
* Fetches benefit finder content of given life event
* Processes the benefit finder content
* Generates JSON data
* Saves JSON data as JSON file

### Benefit finder API module

The `usagov_benefit_finder_api.module` module creates and runs batch job of generating JSON data files
of all life events when content editors add/update a benefit finder content. This keeps benefit finder
JSON data files consistent with benefit finder content.


## Benefit finder page module

The `usagov_benefit_finder_page` custom Drupal module works on defining benefit finder library including
benefit finder app JavaScript and CSS file; providing twig page template with container for benefit
finder app to attach to, with JSON data file path attribute for benefit finder app to fetch data from,
and HTML content for benefit finder app to override.

### Benefit finder App

The `benefit-finder.min.js` is a React program with following functions:
* Fetches JSON data generated by Drupal custom module
* Provides users life event form
* Outputs result about benefit eligibility according to user inputs

The `benefit-finder.min.css` is the associated CSS file.

The `usagov_benefit_finder_page.libraries.yml` defines benefit_finder_app library.

```php
benefit_finder_app:
  version: 1.x
  js:
    js/benefit-finder.min.js: {}
  css:
    theme:
      css/benefit-finder.min.css: {}
```

### Twig Page Template

The `page--benefit-finder-life-event.html.twig` is the page template of life event page:
* Outputs container for React app to attach to
* Outputs draft and published JSON data file path for React app to fetch data from
* Outputs HTML content for React app to override

```php
  <div id="benefit-finder" json-data-file-path="{{ json_data_file_path }}" draft-json-data-file-path="{{ draft_json_data_file_path }}">
    {# app will rehydrate and replace innerHTML #}
    <h1 class="usa-sr-only" id="skip-to-h1" aria-level="1" hidden role="heading">{{ node.label }}</h1>
  </div>
```

### Benefit finder page module

The `usagov_benefit_finder_page.module` module suggests life event page to use the benefit finder page template, and attaches
benefit finder app JavaScript and CSS file to the life event page.


## Benefit finder content module

The `usagov_benefit_finder_content` custom Drupal module works on benefit finder form alter and form validation.

### Benefit finder content module

The `usagov_benefit_finder_content.module` module:
* Validates agency form
* Validates criteria form
* Validates life event form
* Alters benefit edit form
* Validates benefit form

## Benefit finder settings form

The `src/Form/BenefitFinderSettingsForm.php` form provides automate JSON data file generation setting.

## Benefit finder trait

The `src/Traits/BenefitFinderTrait.php` trait provides functions to get benefit finder content.
* Get life event
* Get agency
* Get criteria
* Get benefit
* Get life event form
* Get node of a given node ID and content mode
