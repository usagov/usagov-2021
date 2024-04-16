#!/bin/bash

# On first run, we will not have a node_modules symlink in the
# working directory (which will be a bind mount).
if [ ! -L node_modules ]; then
    ln -s ../node_modules node_modules
fi

# Add instructions to .bashrc, if they are not already present.
if ! grep -q 'EOINSTRS' /root/.bashrc ; then
    cat >> /root/.bashrc <<"EOF"

cat <<EOINSTRS

To run all the tests:
# npx cypress run --spec cypress/e2e

You can run a subset of the tests by specifying a subdirectory, for example:
# npx cypress run --spec cypress/e2e/functional

To run tests interactively:
# npx cypress open

To view the reports in HTML format, open automated_tests/e2e-cypress/reports/index.html

EOINSTRS

# Do we need this to run tests? It appears we do not.
# export NODE_EXTRA_CA_CERTS=/app/zscaler_chain.pem

EOF

    cat >> /root/.bashrc <<EOF
export CYPRESS_BASE_URL=${cypressBaseUrl}
export CYPRESS_CMS_USER=${cypressCmsUser}
export CYPRESS_CMS_PASS=${cypressCmsPass}
EOF

fi

# Just keep the container running so we can shell in and run tests.
tail -f /dev/null
