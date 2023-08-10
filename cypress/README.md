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

How to navigate the Cypress Desktop interface and utilize the test suite.

TODO: add screenshots, code examples and demos; link to more resources

In general, the test scripts (specs) are organized by page. The only exceptions to this are individual specs for the footer and mobile testing.

### Accessibility Testing


### Functional Testing


### Visual Testing


<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- TEST SUITE OVERVIEW -->
## Comprehensive Test Suite Overview
### `cypress.config.js`
explain configs

### `/cypress/e2e`
actual test scripts 

### `/cypress/fixtures`
holds test data 

### `/cypress/support`


<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- HELP -->
## Help
Advise for common problems or issues.

<!-- NEXT STEPS -->
## Next Steps

* Integration with CircleCI

### Resources

* [Overview of Continuous Integration with Cypress](https://docs.cypress.io/guides/continuous-integration/introduction?utm_medium=CI+Prompt+1&utm_campaign=Learn+More&utm_source=Binary%3A+App)
* [Using CircleCI with Cypress](https://docs.cypress.io/guides/continuous-integration/circleci?utm_source=Binary%3A+App&utm_medium=CI+Prompt+1&utm_campaign=Circle&utm_content=Automatic)

<p align="right">(<a href="#readme-top">back to top</a>)</p>