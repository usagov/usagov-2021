# Cypress E2E Tests

These tests run against a live Storybook instance and cover accessibility, routing, data layer, eligibility logic, and UI behaviour across the Benefit Finder application.

All commands should be run from the `app-source/` directory:

```bash
cd web/modules/custom/usagov_benefit_finder/app-source
```

---

## Prerequisites

- Node.js (see `.nvmrc` for required version)
- Chrome installed locally (Cypress runs against Chrome headlessly)
- Dependencies installed: `npm install`

---

## Running the tests

### Option 1 — Full pipeline (build Storybook, then run tests)

This is the standard local workflow. It builds a static Storybook, serves it, and runs Cypress against it in one step.

**Step 1:** Build the Storybook static site

```bash
npm run cy:build:storybook
```

**Step 2:** Start the Storybook server and run all tests

```bash
npm run cy:run:pipeline
```

This uses `concurrently` to spin up `http-server` on port 6006 serving `./storybook-static`, then runs Cypress headlessly in Chrome. The server is killed when the test run finishes.

---

### Option 2 — Run tests against an already-running Storybook

If Storybook is already running (e.g. via `npm run cy:dev:storybook`), you can run the tests directly:

```bash
npm run cy:run:e2e
```

Storybook must be accessible at `http://localhost:6006` (the default `baseUrl` in `cypress.config.js`).

---

### Option 3 — Interactive mode (Cypress UI)

To open the Cypress Test Runner for interactive debugging:

```bash
npx cypress open
```

Then select **E2E Testing** and choose a spec file to run individually.

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

- **Default config:** `cypress.config.js` — targets `http://localhost:6006`, runs in Chrome with 2 retries on failure
- **Production config:** `cypress.prod.links.config.js` — used for link-checking against the live site (`npm run cy:run:prod:links:e2e`)

---

## CI

In CI the pipeline script (`cy:run:pipeline`) is used. It expects `storybook-static/` to already be built before the Docker/CI step runs, or it builds it inline via `cy:build:storybook`.
