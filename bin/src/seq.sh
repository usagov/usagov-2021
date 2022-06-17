#!/bin/bash
echo '=============== bin/init ==============='
bin/init
echo '=============== docker compose up -d ==============='
docker compose up -d
echo '=============== bin/db-update ==============='
bin/db-update
echo '=============== bin/drupal-update ==============='
bin/drupal-update
echo '=============== docker compose up -d ==============='
docker compose up -d
