#!/bin/bash
export nr_proxy=$(echo "${nr_proxy:-}" | sed -e "s/https/http/")

function setIniVal() {
    if [ -z "$1" ]; then
        return 1
    fi
    if grep "$1=" /home/vcap/app/php/etc/php.ini > /dev/null; then
      var=$(echo ${1:-} | sed -e "s|'|\\'|g" | sed -e 's/|/\\|/g' | sed -e 's/\./\\./g' )
      val=$(echo ${2:-} | sed -e "s|'|\\'|g" | sed -e 's/|/\\|/g' | sed -e 's/\./\\./g' )
      sed -i -e "s|;\{0,1\}$var=.*|$var=$val|" /home/vcap/app/php/etc/php.ini
    else
      echo "$1=${2:-}" >> /home/vcap/app/php/etc/php.ini
    fi
}

function setLogFiles () {
  if [ "$1" == "1" ]; then
    touch /home/vcap/app/logs/newrelic.logs
    touch /home/vcap/app/logs/newrelic-daemon.logs
  elif [ "$1" == "0" ]; then
    setIniVal newrelic.logfile /dev/stdout
    setIniVal newrelic.daemon.logfile /dev/stdout
  fi
}

function killNewRelicDaemon() {
    running=$(ps | grep newrelic-daemon | grep -v grep | awk '{print $1}')
    if [ -n "$running" ]; then
        echo -e "$running" | xargs kill
    fi
}

function enableNewRelicProxy () {
  if [ "$1" == "1" ]; then
    setIniVal newrelic.daemon.proxy $nr_proxy
    setIniVal newrelic.daemon.ssl_ca_path /etc/ssl/certs/
    setIniVal newrelic.daemon.ssl_ca_bundle /etc/ssl/certs/ca-certificates.crt

  elif [ "$1" == "0" ]; then
    setIniVal newrelic.daemon.proxy ""
    setIniVal newrelic.daemon.ssl_ca_path ""
    setIniVal newrelic.daemon.ssl_ca_bundle ""
  fi
}


function enableNewRelicProxySSL () {
  if [ "$1" == "1" ]; then
    setIniVal newrelic.daemon.proxy $nr_proxy
    setIniVal newrelic.daemon.ssl_ca_path /etc/ssl/certs/
    setIniVal newrelic.daemon.ssl_ca_bundle /etc/ssl/certs/ca-certificates.crt

  elif [ "$1" == "0" ]; then
    setIniVal newrelic.daemon.proxy $nr_proxy
    setIniVal newrelic.daemon.ssl_ca_path ""
    setIniVal newrelic.daemon.ssl_ca_bundle ""
  fi
}

function enableNewRelic () {
  if [ "$1" == "1" ]; then
    setIniVal newrelic.enabled true
    setIniVal newrelic.daemon.dont_launch 0

    setIniVal newrelic.daemon.collector_host gov-collector.newrelic.com
    setIniVal newrelic.daemon.address @newrelic-daemon
    setIniVal newrelic.daemon.port /home/vcap/app/newrelic/daemon.sock

    setIniVal newrelic.daemon.loglevel debug
    setIniVal newrelic.loglevel verbosedebug

    if [ -n "$nr_proxy" ]; then
      enableNewRelicProxy 1;
    else
      enableNewRelicProxy 0;
    fi

  elif [ "$1" == "0" ]; then
    setIniVal newrelic.enabled false
    setIniVal newrelic.daemon.dont_launch 3

  fi
}

function makeNoise() {
  makeWebNoise
  makePhpNoise ${1:-NOISE}
}

function makeWebNoise() {
  # echo "Curl newrelic.com no-proxy: $(HTTP='' HTTPS='' curl -sI https://newrelic.com/?inside=2 | grep 'HTTP')"
  echo "Curl 127.0.0.1:8080: $(curl -sI http://127.0.0.1:8080/1.html | grep 'HTTP')"
  echo "Curl 127.0.0.1:8080: $(curl -sI http://127.0.0.1:8080/2.html | grep 'HTTP')"
  echo "Curl nr-php-test.app.cloud.gov: $(curl -sI https://nr-php-test.app.cloud.gov/3.html | grep 'HTTP')"
  echo "Curl nr-php-test.app.cloud.gov: $(curl -sI https://nr-php-test.app.cloud.gov/4.html | grep 'HTTP')"
  echo "Curl google.com (should be 200|301): $(curl -sI https://google.com | grep 'HTTP')"
  echo "Curl yahoo.com (should be 403): $(curl -sI https://yahoo.com | grep 'HTTP')"
}

function makePhpNoise() {
    /home/vcap/app/php/bin/php -c /home/vcap/app/php/etc/ -r "echo \"${1:-NOISE}-1\n\";"
    sleep 1
    /home/vcap/app/php/bin/php -c /home/vcap/app/php/etc/ -r "echo \"${1:-NOISE}-2\n\";"
}

function getState() {
  echo "HTTP Proxies:"
  echo "  http_proxy=$(echo ${http_proxy:-})"
  echo "  https_proxy=$(echo ${https_proxy:-})"
  echo "  HTTP_PROXY=$(echo ${HTTP_PROXY:-})"
  echo "  HTTPS_PROXY=$(echo ${HTTPS_PROXY:-})"
  echo

  echo "NewRelic Proxy:"
  echo "  nr_proxy=$(echo ${nr_proxy:-})"
  cat /home/vcap/app/php/etc/php.ini | grep newrelic.daemon.proxy
  echo

  echo "New Relic Daemon:"
  echo "$(ps aux | grep newrelic-daemon | grep -v grep)"
  echo

}