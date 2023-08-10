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



<!-- USAGE EXAMPLES -->
## Usage

Use this space to show useful examples of how a project can be used. Additional screenshots, code examples and demos work well in this space. You may also link to more resources.

_For more examples, please refer to the [Documentation](https://example.com)_

<p align="right">(<a href="#readme-top">back to top</a>)</p>


<!-- ROADMAP -->
## Roadmap

- [ ] Feature 1
- [ ] Feature 2
- [ ] Feature 3
    - [ ] Nested Feature

See the [open issues](https://github.com/github_username/repo_name/issues) for a full list of proposed features (and known issues).

<p align="right">(<a href="#readme-top">back to top</a>)</p>