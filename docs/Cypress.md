## Tests Organization

Tests are organized by the feature or component they belong to. To determine where to place a test:

- If a test applies to all instances of the componenet throughout the site it should be placed in that component's test file even though it may be found in a feature.
  - For example,  a test that checks the opening and closing functionality of the usa-accordion components applies to all the components on the site, not just those in the federal directories or mobile menues.
- If a test applies only to that component's instance in the feature it should be placed in the feature.
  - For example, checking that federal directory agency accordions have the agency description, website, phone number, and contact.

## Test Suites

Different combinations of tests are organized into various test suites.
These test suites call the tests defined in the feature or component directories.

- Regression Testing
- Pre PR Testing
- A11Y Testing