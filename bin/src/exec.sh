#!/bin/bash
echo 'update database with backed up sql'
docker exec -it -u nginx cms sh -c "mysql -h database -u root -pmysql drupal < usagov.sql"
docker exec -it cms sh -c "drush en -y allowed_formats autologout image_style_warmer usa_admin_styles usa_twig_vars"
docker exec -it -u nginx cms sh -c "drush cim -y"
docker exec -it cms sh -c 'rm -rf s3/local/cms/public/css/* s3/local/cms/public/css/*'
docker exec -it -u nginx cms sh -c 'drush -y s3fs-copy-local'
docker exec -it -u nginx cms sh -c 'drush cr && drush cron'
