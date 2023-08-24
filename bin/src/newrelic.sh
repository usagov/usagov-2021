#!/bin/sh
if [ $(uname -m) != 'aarch64' ]; then
  export NR_VERSION=$(curl -sS https://download.newrelic.com/php_agent/release/ | sed -n 's/.*>\(.*linux\-musl\).tar.gz<.*/\1/p') \
    && cd /tmp \
    && curl -L "https://download.newrelic.com/php_agent/release/${NR_VERSION}.tar.gz" | tar -C /tmp -zx \
    && NR_INSTALL_USE_CP_NOT_LN=1 NR_INSTALL_USE_CP_NOT_LN=1 /tmp/newrelic-php5-*/newrelic-install install \
    && rm -rf /tmp/newrelic-php5-* /tmp/nrinstall* \
    && sed -i \
      -e "s/;\?newrelic.appname =.*/newrelic.appname = \"USA.gov\"/" \
      -e "s/;\?newrelic.process_host.display_name =.*/newrelic.process_host.display_name = usa-cms-local/" \
      -e 's/;\?newrelic.daemon.app_connect_timeout =.*/newrelic.daemon.app_connect_timeout=15s/' \
      -e 's/;\?newrelic.daemon.start_timeout =.*/newrelic.daemon.start_timeout=5s/' \
      -e 's/;\?newrelic.logfile =.*/newrelic.logfile = \/dev\/stdout/' \
      -e 's/;\?newrelic.daemon.logfile =.*/newrelic.daemon.logfile = \/dev\/stdout/' \
      -e 's/;\?newrelic.daemon.loglevel =.*/newrelic.daemon.loglevel = "warning"/' \
      -e 's/;\?newrelic.daemon.collector_host =.*/newrelic.daemon.collector_host = "gov-collector.newrelic.com"/' \
      -e 's/;\?newrelic.framework =.*/newrelic.framework = "drupal8"/' \
      -e 's/;\?newrelic.loglevel =.*/newrelic.loglevel = "warning"/' \
      -e 's/;\?newrelic.enabled =.*/newrelic.enabled = false/' \
      /etc/php81/conf.d/newrelic.ini
fi
