#!/bin/bash
echo '=============== bin/init ==============='
bin/init
echo '=============== bin/drupal-update ==============='
bin/drupal-update
echo '=============== bin/db-update ==============='
bin/db-update
echo '=============== docker compose up -d ==============='
docker compose up -d