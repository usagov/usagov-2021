#!/bin/bash

# On first run, we will not have a node_modules symlink in the
# working directory (which will be a bind mount).
if [ ! -f node_modules ]; then
    ln -s ../node_modules node_modules
fi

# Add instructions to .bashrc, if they are not already present.
if ! grep -q 'EOINSTRS' /root/.bashrc ; then
    cat >> /root/.bashrc <<"EOF"

cat <<EOINSTRS

To run all the tests:
# cypress run --spec cypress/e2e

You can run a subset of the tests by specifying a subdirectory, for example:
# cypress run --spec cypress/e2e/functional

To view the reports in HTML format, open automated_tests/e2e-cypress/reports/index.html

EOINSTRS

EOF
fi

# Just keep the container running so we can shell in and run tests.
tail -f /dev/null &
