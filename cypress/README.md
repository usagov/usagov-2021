# Automated Testing with Cypress

An automated test suite based off the USA.gov manual regression checklist.

<!-- GETTING STARTED -->
## Getting Started
**Note: This guide assumes you have already set up your local development environment for the USA.gov site.**

### System Requirements

* OS: macOS 10.9 and above, Linux Ubuntu 12.04 and above, Windows 7 and above
* Node.js: 16.x, 18.x, 20.x and above

### Dev Dependencies

* [cypress](https://github.com/cypress-io/cypress)
* [cypress-image-diff](https://github.com/uktrade/cypress-image-diff)
* [cypress-axe](https://github.com/component-driven/cypress-axe)
* [cypress-real-events](https://github.com/dmtrKovalenko/cypress-real-events)

### Set Up

To get a local copy up and running follow these simple steps.

1. If you haven't already, open up your IDE/terminal and start your local dev server.
    ```
    docker compose up
    ```
2. Open another terminal window, navigate to the **root directory**, and run the following command to open Cypress:
    ```
    npx cypress open
    ```
    A window from the Cypress desktop app should pop up prompting you to choose from 2 testing types. 

3. Select E2E Testing. Then select the browser you would like to test from. That's it, you're ready to start testing!

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- USAGE -->
## Usage

How to navigate the Cypress Desktop interface, run tests, and utilize the test suite.

TODO: add screenshots, code examples and demos; link to more resources

### Cypress Desktop Navigation
After selecting the testing browser you should be brought to a tab listing all the specs (test scripts) in the project. Scripts are separated into three directories: accessibility, functional, and visual. 

![test](https://drive.google.com/file/d/1fiqm6fpqcae91XFhF1xyNpytsHLh7Spw/view?usp=sharing)

To run a test script simply click on its name, or hover over a directory to have the option to run multiple test scripts at once.

### Functional Testing

The functional test scripts are organized by page based on the regression checklist (plus individual specs for the footer and mobile testing).

Test cases are labeled with their ID from the regression checklist (BTE # for English site tests or BTS # for Spanish site tests).

A few test cases (BTE/BTS 38-44, 49) have been excluded from functional testing due to being a purely visual-based test (checking that something looks correct).

### Accessibility Testing


### Visual Testing


<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- CODEBASE OVERVIEW -->
## Codebase Overview
### `cypress.config.js`
This is the Cypress configuration file, where you can set the base URL you're testing from (`http://localhost`), default viewport dimensions, and more.

This is also where you'll find the `setupNodeEvents` function if you need to run code in Node or add a plugin to your project. 

Whenever you modify your configuration file, Cypress will automatically reboot itself and kill any open browsers. This is normal. Click on the spec file again to relaunch the browser.

### `/cypress/e2e`
Contains all frontend test scripts organized into accessibility, functional, and visual tests. Within each of these directories, test scripts are then separated into English site tests and Spanish site tests.

### `/cypress/fixtures`
Holds test data: 
* `socials.json` contains social media info found in the footer
    * usages: `/cypress/e2e/functional/eng/footer.cy.js`, `/cypress/e2e/functional/es/footer_es.cy.js`
* `subpaths.json` contains all example url subpaths listed for validation on the regression checklist
    * usages: `/cypress/e2e/accessibility/axe_eng.cy.js`, `/cypress/e2e/accessibility/axe_es.cy.js`, `/cypress/e2e/visual/full_pg_visual_eng.cy.js`, `/cypress/e2e/visual/full_pg_visual_es.cy.js`

### `/cypress/support`
custom commands and package imports

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- WRITING TEST SCRIPTS -->
## Writing Test Scripts


### Element Selection


### DOM Assertions


### CSS Assertions 
Most visual validation can and should be done with screenshot comparisons, but CSS validation comes in handy when visual testing fails. 

### Accessibility Testing


### Visual Testing


<!-- NEXT STEPS -->
## Next Steps

* Integration with CircleCI

### Resources

* [Overview of Continuous Integration with Cypress](https://docs.cypress.io/guides/continuous-integration/introduction?utm_medium=CI+Prompt+1&utm_campaign=Learn+More&utm_source=Binary%3A+App)
* [Using CircleCI with Cypress](https://docs.cypress.io/guides/continuous-integration/circleci?utm_source=Binary%3A+App&utm_medium=CI+Prompt+1&utm_campaign=Circle&utm_content=Automatic)

<p align="right">(<a href="#readme-top">back to top</a>)</p>