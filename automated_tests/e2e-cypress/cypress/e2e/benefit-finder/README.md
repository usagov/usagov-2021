# Benefit Finder Cypress Tests

This directory contains Cypress integration tests for the Benefit Finder React application as integrated into the Drupal CMS.

## Directory Structure

```
cypress/
├── e2e/
│   └── benefit-finder/
│       ├── benefit-finder-integration.cy.js  # Main integration tests
│       └── benefit-finder-accessibility.cy.js # Accessibility tests
├── fixtures/
│   └── benefit-finder/
│       └── benefits-eligibility.json         # Test data fixtures
└── support/
    └── benefit-finder/
        ├── pageObjects.js                     # Page object definitions
        └── utils.js                           # Utility functions
```

## Test Files

### benefit-finder-integration.cy.js
Integration tests that verify:
- Benefit finder loads on life event pages (death, retirement, disability)
- JavaScript and CSS bundles are loaded correctly
- JSON data endpoints are accessible
- React components render properly
- Basic user interactions work

### benefit-finder-accessibility.cy.js
Accessibility tests that verify:
- WCAG 2.1 AA compliance
- Proper heading hierarchy
- Keyboard accessibility
- ARIA labels on form controls
- Focus management

## Running the Tests

### Using the Cypress Container (Recommended)

1. **Start your local development environment:**
   ```bash
   cd /Users/jacobyeager/Sites/usagov-2021
   bin/bootstrap
   ```

2. **Start the Cypress container:**
   ```bash
   docker-compose up cypress
   ```

3. **Access the Cypress container:**
   ```bash
   docker exec -it cypress bash
   ```

4. **Run all benefit finder tests:**
   ```bash
   npx cypress run --spec "cypress/e2e/benefit-finder/**/*.cy.js"
   ```

5. **Run specific test file:**
   ```bash
   npx cypress run --spec "cypress/e2e/benefit-finder/benefit-finder-integration.cy.js"
   ```

6. **Run tests interactively (with GUI):**
   ```bash
   npx cypress open
   ```
   Then select the benefit-finder tests from the GUI.

### Using Local Cypress Installation

If you prefer to run Cypress locally without Docker:

1. **Navigate to the test directory:**
   ```bash
   cd automated_tests/e2e-cypress
   ```

2. **Install dependencies (if not already done):**
   ```bash
   npm install
   ```

3. **Run benefit finder tests:**
   ```bash
   npx cypress run --spec "cypress/e2e/benefit-finder/**/*.cy.js"
   ```

## Configuration

The tests use the main Cypress configuration from `cypress.config.js` which sets:
- **baseUrl:** `http://cms-usagov.docker.local`
- **viewport:** 1280x800
- **retries:** 0 (in open mode), 0 (in run mode)

## Test Data

Test data is stored in `cypress/fixtures/benefit-finder/`:
- **benefits-eligibility.json** - Contains test scenarios and expected results for various eligibility criteria

## Utilities

Support utilities are available in `cypress/support/benefit-finder/`:
- **utils.js** - Helper functions for data layer filtering, date manipulation, URL encoding
- **pageObjects.js** - Page object patterns for benefit finder elements

## Customization

### Adjusting Selectors

The integration tests use generic selectors that may need adjustment based on your Drupal implementation:

```javascript
// In benefit-finder-integration.cy.js, update these selectors:
cy.get('#benefit-finder-app, [data-benefit-finder]')  // React app container
cy.request('/benefit-finder-death-data.json')         // JSON API endpoint
```

### Adding Custom Commands

To add Cypress custom commands for benefit finder testing, edit:
```
cypress/support/commands.js
```

Example custom command:
```javascript
Cypress.Commands.add('fillBenefitFinderForm', (formData) => {
  // Your custom form filling logic
})
```

## Integration with CI/CD

These tests are automatically included when running the full Cypress test suite in CircleCI or other CI/CD pipelines.

## Troubleshooting

### React App Not Loading
- Ensure your local Drupal site is running (`bin/bootstrap`)
- Check that the benefit finder module is enabled
- Verify the life event pages exist: `/death-of-a-loved-one`, `/retirement`, `/disability`

### Tests Timing Out
- Increase timeout values in test specs: `cy.get('element', { timeout: 10000 })`
- Check Docker container logs for errors

### Selectors Not Found
- Use Cypress interactive mode to inspect elements: `npx cypress open`
- Update selectors in test files to match your actual Drupal/React implementation

## Related Documentation

- [Benefit Finder Module README](../../../web/modules/custom/usagov_benefit_finder/README.md)
- [Main Cypress Configuration](../../cypress.config.js)
- [px-benefit-finder Repository](https://github.com/GSA/px-benefit-finder)
