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

### Testing with Cypress Desktop 
After selecting the testing browser you should be brought to a tab listing all the specs (test scripts) in the project. Scripts are separated into three directories: accessibility, functional, and visual. 

![test]()

To run a test script simply click on its name, or hover over a directory to have the option to run multiple test scripts at once.

### Testing through Terminal
`./node_modules/.bin/cypress run --spec cypress/e2e/functional/eng/error_page.cy.js`
`cypress run --spec cypress/e2e/accessibility`

### Excluding/Isolating Test Cases
`.only` and `.not`

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

<!-- TEST SCRIPTS -->
## Test Scripts
Cypress is built with the Mocha Javascript testing framework and Chai assertion library, which support BDD / TDD assertions.

### Element Selection


### DOM Assertions


### CSS Assertions 
Most visual validation can and should be done with screenshot comparisons, but CSS validation comes in handy when visual testing fails. 

To learn more about firing native system events in Cypress visit the [cypress-real-events](https://github.com/dmtrKovalenko/cypress-real-events).

### Functional Testing

The functional test scripts are organized by page based on the regression checklist (plus individual specs for the footer and mobile testing).

Test cases are labeled with their ID from the regression checklist (BTE # for English site tests or BTS # for Spanish site tests).

A few test cases (BTE/BTS 38-44, 49) have been excluded from functional testing due to being a purely visual-based test (checking that something looks correct).

### Accessibility Testing
The `cypress-axe` plugin is used to validate a11y complicance on the site. The a11y test scripts loop through page urls stored in `subpaths.json` and runs axe to verify that each page meets WCAG 2.0 Level AA conformance.

To learn more about axe visit the [cypress-axe documentation](https://github.com/component-driven/cypress-axe).

### Visual Testing
Similar to a11y testing, the visual testing scripts loop through page urls stored in `subpaths.json`. It takes a screenshot of each page and runs a comparison with the existing baseline screenshot with commands from the `cypress-image-diff` plugin.

All screenshot images are stored in the `/cypress-visual-screenshots` directory. Baseline screenshots (what the page should look like) are stored in the `/baseline` subdirectory, new screenshots (what the page actually looks like currently after new changes have been applied) are stored in the `/comparison` subdirectory, and images highlighting the differences (if any) between the baseline and new screenshots are stored in the `/diff` subdirectory.

To learn more about using the screenshot plugin visit the [cypress-image-diff documentation](https://github.com/uktrade/cypress-image-diff).

<!-- REPORTING -->
## Test Results and Reporting


<!-- BUGS -->
## Bugs, (Test) Failures, and Work in Progress


<!-- NEXT STEPS -->
## Next Steps

* Integration with CircleCI

### Resources

* [Overview of Continuous Integration with Cypress](https://docs.cypress.io/guides/continuous-integration/introduction?utm_medium=CI+Prompt+1&utm_campaign=Learn+More&utm_source=Binary%3A+App)
* [Using CircleCI with Cypress](https://docs.cypress.io/guides/continuous-integration/circleci?utm_source=Binary%3A+App&utm_medium=CI+Prompt+1&utm_campaign=Circle&utm_content=Automatic)

<p align="right">(<a href="#readme-top">back to top</a>)</p>