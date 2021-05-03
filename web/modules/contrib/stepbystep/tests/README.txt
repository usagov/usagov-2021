This module uses PHPUnit functional tests to follow drupal.org standards.

To run tests, execute the command below in the web root of your Drupal site,
where 'www-data' is the name of the user your web server runs as, and
'http://localhost/drupal8/web' is the URL to the root of your site.

sudo -u www-data php core/scripts/run-tests.sh --verbose --sqlite /tmp/test.sqlite --url http://localhost/drupal8/web stepbystep

If you receive an error that sqlite is missing, install the php-sqlite3 Debian
package.
