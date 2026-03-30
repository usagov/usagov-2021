# Cypress E2E Tests

These tests run against a live Storybook instance and cover accessibility, routing, data layer, eligibility logic, and UI behaviour across the Benefit Finder application.

All commands should be run from the `app-source/` directory:

```bash
cd web/modules/custom/usagov_benefit_finder/app-source
```

---

## Prerequisites

- Node.js (see `.nvmrc` for required version)
- `npm install` has been run from `app-source/`
- Chrome installed locally for `npm run cy:run:e2e`

---

## Running the tests

### Recommended local workflow

Use one terminal to start Storybook in test mode and a second terminal to run Cypress.

**Terminal 1:** Start Storybook on `http://localhost:6006`

```bash
npm run cy:dev:storybook
```

This command runs the Cypress prebuild step first, then starts Storybook in dev mode on port `6006`.

**Terminal 2:** Run the Storybook E2E suite in headless Chrome

```bash
npm run cy:run:e2e
```

`npm run cy:run:e2e` expects the app to already be available at `http://localhost:6006`, which is the default `baseUrl` in `cypress.config.js`.

---

### Static Storybook workflow

If you want to test the built static Storybook instead of the dev server, use this flow:

**Step 1:** Build the static Storybook files

```bash
npm run cy:build:storybook
```

**Step 2:** Serve `storybook-static/`

```bash
npx http-server ./storybook-static --port 6006 --silent
```

**Step 3:** In a second terminal, run Cypress

```bash
npm run cy:run:e2e
```

Unlike CI, this is a manual two-terminal workflow. Stop the `http-server` process yourself when the test run is finished.

---

### Interactive mode

To use the Cypress app for debugging:

**Step 1:** Start Storybook first

```bash
npm run cy:dev:storybook
```

**Step 2:** Open Cypress

```bash
npx cypress open
```

Then select **E2E Testing** and choose a spec file to run individually.

---

### CI helper script

`npm run cy:run:pipeline` is not a full local pipeline runner.

It currently starts `http-server` for `storybook-static/` and then keeps serving until the process is stopped. In GitHub Actions this is used as the `start` command for `cypress-io/github-action`, and the action itself launches Cypress after the server is reachable.

If you run `npm run cy:run:pipeline` locally by itself, it will look like it is hanging because no Cypress command is started by that script.

---

## Spec files

All specs live in `cypress/e2e/storybook/`:

| File | What it tests |
|---|---|
| `axe-a11y.cy.js` | Automated accessibility (axe-core) across all stories |
| `aria-attribute-state.cy.js` | ARIA attribute state changes during interaction |
| `benefitAccordionGroup.cy.js` | Benefit accordion expand/collapse behaviour |
| `client-router.cy.js` | Client-side routing between views |
| `dataLayer.cy.js` | GTM data layer pushes |
| `error-message-display.cy.js` | Validation error message rendering |
| `modal.cy.js` | Modal open/close and focus trapping |
| `openAllAccordions.cy.js` | "Open all" accordion toggle |
| `selected-criteria-eligibility-benefits.cy.js` | Eligibility results for selected criteria |

---

## Configuration

- **Default config:** `cypress.config.js` targets `http://localhost:6006`, retries failed runs twice in headless mode, and excludes `cypress/e2e/usagov-public-site/*.cy.js`
- **Production links config:** `cypress.prod.links.config.js` targets `https://www.usa.gov` and is wired to `cypress/e2e/usagov-public-site/links.cy.js`

At the time of this update, the checked-in Storybook suite lives under `cypress/e2e/storybook/`. If you need the production links workflow, confirm that the `usagov-public-site` spec file exists in your branch before using `npm run cy:run:prod:links:e2e`.

---

## CI

GitHub Actions builds Storybook with `npm run cy:build:storybook`, then uses `npm run cy:run:pipeline` only to start the local server. Cypress is started separately by `cypress-io/github-action`, which is why the CI workflow can use `cy:run:pipeline` successfully even though it does not directly call `cypress run`.
