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

3. Select E2E Testing. Then select the browser you would like to test from. That's it!

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- TEST SUITE OVERVIEW -->
## Comprehensive Test Suite Overview
### `cypress.config.js`

### `/cypress/e2e`

### `/cypress/fixtures`

### `/cypress/e2e`

### `/cypress/support`

<!-- USAGE EXAMPLES -->
## Usage

Use this space to show useful examples of how a project can be used. Additional screenshots, code examples and demos work well in this space. You may also link to more resources.

### Accessibility Testing


### Functional Testing


### Visual Testing


<p align="right">(<a href="#readme-top">back to top</a>)</p>


<!-- HELP -->
## Help
Advise for common problems or issues.

<!-- ROADMAP -->
## Roadmap

- [ ] Integratin with CircleCI
- [ ] Feature 3
    - [ ] Nested Feature

<p align="right">(<a href="#readme-top">back to top</a>)</p>