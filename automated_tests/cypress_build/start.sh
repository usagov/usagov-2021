#!/bin/bash

# On first run, we will not have a node_modules symlink.
# Create that and also update the .bashrc with instructions.
if [ ! -f node_modules ]; then
    ln -s ../node_modules node_modules
fi

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

# Define cleanup procedure
cleanup() {
    echo "Container stopped, performing cleanup..."
    # remove the node_modules symlink
    rm node_modules
}

# Trap SIGTERM
trap 'cleanup' SIGTERM

# Just keep the container running so we can shell in and run tests.
tail -f /dev/null &

# Wait
wait $!
