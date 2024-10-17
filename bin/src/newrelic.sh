#!/bin/sh

# TODO: why the [ $(uname -m) != 'aarch64' ] clause? Was this meant to exclude local install?
if [ "$(uname -m)" != 'aarch64' ]; then
  export NR_VERSION_NUMBER='11.2.0.15'

  NR_VERSION="newrelic-php5-$NR_VERSION_NUMBER-linux-musl"
  export NR_VERSION

  cd /tmp || exit

  curl -L "https://download.newrelic.com/php_agent/archive/${NR_VERSION_NUMBER}/${NR_VERSION}.tar.gz" | tar -C /tmp -zx

  export NR_INSTALL_USE_CP_NOT_LN=1
  /tmp/newrelic-php5-*/newrelic-install install

  rm -rf /tmp/newrelic-php5-* /tmp/nrinstall*

  sed -i \
      -e "s/;\?newrelic.appname =.*/newrelic.appname = \"USA.gov\"/" \
      -e "s/;\?newrelic.process_host.display_name =.*/newrelic.process_host.display_name = usa-cms-local/" \
      -e 's/;\?newrelic.daemon.app_connect_timeout =.*/newrelic.daemon.app_connect_timeout=15s/' \
      -e 's/;\?newrelic.daemon.start_timeout =.*/newrelic.daemon.start_timeout=5s/' \
      -e 's/;\?newrelic.logfile =.*/newrelic.logfile = \/dev\/stderr/' \
      -e 's/;\?newrelic.daemon.logfile =.*/newrelic.daemon.logfile = \/dev\/stderr/' \
      -e 's/;\?newrelic.daemon.loglevel =.*/newrelic.daemon.loglevel = "info"/' \
      -e 's/;\?newrelic.daemon.collector_host =.*/newrelic.daemon.collector_host = "gov-collector.newrelic.com"/' \
      -e 's/;\?newrelic.framework =.*/newrelic.framework = "drupal8"/' \
      -e 's/;\?newrelic.loglevel =.*/newrelic.loglevel = "info"/' \
      -e 's/;\?newrelic.enabled =.*/newrelic.enabled = false/' \
      -e 's/;\?newrelic.error_collector.record_database_errors =.*/newrelic.error_collector.record_database_errors = true/' \
      /etc/php81/conf.d/newrelic.ini

  NR_LATEST_VERSION="$(curl -sS https://download.newrelic.com/php_agent/release/ | sed -n 's/.*>\(.*linux\-musl\).tar.gz<.*/\1/p')"
  export NR_LATEST_VERSION
  if [ "$NR_VERSION" != "$NR_LATEST_VERSION" ]; then
    # TODO: we never see this warning because it is emitted during image build.
    echo "WARNING: New Relic latest version is '${NR_LATEST_VERSION}'; we are installing '${NR_VERSION}'"
  fi
fi
