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
* [cypress-mochawesome-reporter](https://github.com/LironEr/cypress-mochawesome-reporter)

### Set Up

To get a local copy up and running follow the steps found in the main README of [usa.gov](https://github.com/usagov/usagov-2021)

1. Make sure that you're in your root directory of your local repo. If you haven't already, open up your IDE/terminal and start your local dev server.
    ```
    bin/init
    docker compose up
    ```

2. Open another terminal window, again, make sure you've navigated to the **root directory**. Then, run the commands linked to downloading. This will install Cypress locally as a dev dependency for your project.:
    ```
    npm i cypress --save-dev  
    ```

3. To install the dev dependencies, run the following commands in the same terminal window:
    ```
    npm i -D cypress-image-diff-js
    npm i --save-dev cypress-axe
    npm i --save-dev cypress axe-core
    npm i cypress-real-events
    npm i --save-dev cypress-mochawesome-reporter
    ```

4. Next, run the following command to open Cypress:
    ```
    npx cypress open
    ```
    A window from the Cypress desktop app should pop up prompting you to choose from 2 testing types. 

4. Select E2E Testing. Then select the browser you would like to test from. That's it, you're ready to start testing!

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- USAGE -->
## Usage
How to navigate the Cypress Desktop interface, run tests, and utilize the test suite.

### Testing with Cypress Desktop 
After selecting the testing browser you should be brought to a tab listing all the specs (test scripts) in the project. Scripts are separated into three directories: accessibility, functional, and visual. 

To run a test script simply click on its name, or hover over a directory to have the option to run multiple test scripts at once.

### Testing through Terminal
To run tests or debug without opening Cypress Desktop, use the `cypress run --spec <filepath>` command from your root directory. 

**Note: This will generate a test report, but it is also a bit slower than using Cypress Desktop.**

#### Examples:

Run all tests in test suite:

    cypress run --spec cypress/e2e
    

Run all accessibility tests:

    cypress run --spec cypress/e2e/accessibility

Run the English site functional test for the error page: 

    cypress run --spec cypress/e2e/functional/eng/error_page.cy.js

If the above command doesn't work try this one:

    ./node_modules/.bin/cypress run --spec <filepath>


#### Test Results and Reporting
Running tests through the terminal will automatically generate an test report that you can open locally in your browser once the tests are done running. The html file can be found in `cypress/reports/html/index.html`.

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- CODEBASE OVERVIEW -->
## Codebase Overview
### `cypress.config.js`
This is the Cypress configuration file, where you can set the base URL you're testing from (`http://localhost`), default viewport dimensions, and more.

This is also where you'll find the `setupNodeEvents` function if you need to run code in Node or add a plugin to your project. 

Whenever you modify your configuration file, Cypress will automatically reboot itself and kill any open browsers. This is normal. Click on the spec file again to relaunch the browser.

### `/cypress/e2e`
Contains all frontend test scripts organized into accessibility, functional, and visual tests. Within each of these directories, test scripts are then separated into English site tests and Spanish site tests. Each test is labeled with the corresponding regression test.

### `/cypress/fixtures`
Holds test data: 
* `socials.json` contains social media info found in the footer
    * usages: `/cypress/e2e/functional/eng/footer.cy.js`, `/cypress/e2e/functional/es/footer_es.cy.js`
* `subpaths.json` contains all example url subpaths listed for validation on the regression checklist
    * usages: `/cypress/e2e/accessibility/axe_eng.cy.js`, `/cypress/e2e/accessibility/axe_es.cy.js`, `/cypress/e2e/visual/full_pg_visual_eng.cy.js`, `/cypress/e2e/visual/full_pg_visual_es.cy.js`

### `/cypress/support`

#### `commands.js`
We currently don't use any custom commands within the test suite, but this is where you can create various custom commands and overwrite existing commands.

The file contains a few simple examples of commands you can create. 

For more comprehensive examples of custom commands please read more here: https://on.cypress.io/custom-commands

#### `e2e.js`

This file is processed and loaded automatically before your test files.
It is used to customize global configuration and behavior that modifies Cypress.

You can change the location of this file or turn off automatically serving support files with the 'supportFile' configuration option.

Read more here: https://on.cypress.io/configuration

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- TEST SCRIPTS -->
## Test Scripts
How to write and debug test scripts. 

### Element Selection
Our method of selecting elements currently relies on the DOM structure of the page. We use functions like `.get` and `.find` to query elements. There are a lot of other options for element selection in Cypress as well, and their documentation can be found [here](https://example.cypress.io/commands/querying)

Within the get function, a query parameter can be a class, id, etc., but there is a best practice use case of a 'data-cy' identifier. This would be a next steps feature as it would ensure 100% that tests are still able to run no matter the change to the website. Linked here are some cypress [best practices](https://docs.cypress.io/guides/references/best-practices).

### DOM Assertions
Cypress is built with the Mocha Javascript testing framework and Chai assertion library, which support BDD / TDD assertions.
Most DOM assertions are set up through a `.should` function and you can get a comprehensive list of these assertions [here](https://docs.cypress.io/guides/references/assertions).

### CSS Assertions 
Most visual validation can and should be done with screenshot comparisons, but CSS validation comes in handy when visual testing fails. 

Cypress can't detect state changes after events, so we use a plugin to check things like hover state.

Example:

    // Css check for hover state 
    cy.wrap(el)
        .realHover()
        .should('have.css', 'background-color', 'rgb(204, 236, 242)')

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

All screenshot images are stored in the `/cypress-visual-screenshots` directory. 
Subdirectories:
* `/baseline`: what the page should look like
* `/comparison`: new screenshots of what the page actually looks like after new changes have been applied 
* `/diff`:images highlighting the differences (if any) between the baseline and new screenshots

To learn more about using the screenshot plugin visit the [cypress-image-diff documentation](https://github.com/uktrade/cypress-image-diff).

<!-- BUGS -->
## Bugs, (Test) Failures, and Work in Progress
* Some tests do not work since we have an outdated data set for the USAgov website currenlty loaded on our branch
* BTE/BTS 47: Could not get scam report form to work (clicking through a multipage form)
* BTE/BTS 48: Could not get functional test for the federal directory query to work
* BTE/BTS 51: Testing each individual state directory page 


### Conditional Visual Testing
Verifies if a certain element or part of the page looks correct after a certain action is performed.

Example: checking an accordion looks correct both before and after it's been expanded.

Currently the only conditional visual testing script is `cypress/e2e/visual/home_page_visual.cy.js`

<!-- NEXT STEPS -->
## Next Steps

* Integration with CircleCI
* Selection with best practices

### Resources

* [Overview of Continuous Integration with Cypress](https://docs.cypress.io/guides/continuous-integration/introduction?utm_medium=CI+Prompt+1&utm_campaign=Learn+More&utm_source=Binary%3A+App)
* [Using CircleCI with Cypress](https://docs.cypress.io/guides/continuous-integration/circleci?utm_source=Binary%3A+App&utm_medium=CI+Prompt+1&utm_campaign=Circle&utm_content=Automatic)

<p align="right">(<a href="#readme-top">back to top</a>)</p>