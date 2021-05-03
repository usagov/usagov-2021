#!/bin/bash

# Run either PHPUnit tests or PHP_CodeSniffer tests on Travis CI, depending
# on the passed in parameter.

mysql_to_ramdisk() {
    sudo service mysql stop
    sudo mv /var/lib/mysql /var/run/tmpfs
    sudo ln -s /var/run/tmpfs /var/lib/mysql
    sudo service mysql start
}

case "$1" in
    PHP_CodeSniffer)
        cd ${TRAVIS_BUILD_DIR}/
        ./vendor/bin/phpcs
        exit $?
        ;;
    8.*.x)
        mysql_to_ramdisk
        cd ${TRAVIS_BUILD_DIR}/
        ls -la
        ls -la ../build/modules/contrib/
        exit $?
        ;;
    *)
        echo "Unknown test '$1'"
        exit 1
esac
