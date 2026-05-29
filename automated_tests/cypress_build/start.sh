#!/bin/bash

cd /app || exit 1

# Add instructions to .bashrc, if they are not already present.
if ! grep -q 'EOINSTRS' /root/.bashrc ; then
    cat >> /root/.bashrc <<"EOF"

cat <<EOINSTRS

To run the regression test suite:
# npm run cy:run:regression

You can run a smaller subset of the regression tests by specifying a file or directory, for example:
# npm run cy:run -- --spec cypress/e2e/regression_testing/homepage.cy.js

To run every Cypress spec, including older and example tests:
# npm run cy:run

To run tests interactively:
# npm run cy:open

The Cypress scripts default to Chromium. You can override that by setting
the CYPRESS_BROWSER environment variable before running a script.

To view the reports in HTML format, open automated_tests/e2e-cypress/cypress/reports/html/index.html

EOINSTRS

# Do we need this to run tests? It appears we do not.
# export NODE_EXTRA_CA_CERTS=/app/zscaler_chain.pem

EOF

    cat >> /root/.bashrc <<EOF
export CYPRESS_BASE_URL=${cypressBaseUrl}
export CYPRESS_CMS_USER=${cypressCmsUser}
export CYPRESS_CMS_PASS=${cypressCmsPass}
export CYPRESS_PROJECT_DIR=/app/e2e-cypress
export CYPRESS_BROWSER=chromium
EOF

fi

# Just keep the container running so we can shell in and run tests.
tail -f /dev/null
