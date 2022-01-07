#!/usr/bin/env bash

drush site-install standard --sites-subdir=default -y
drush scr create_articles.php --script-path=$PWD
drush en tome -y
time drush tome:export -y
drush cr
time drush tome:static -y
drush site-install standard --sites-subdir=default -y
drush en tome -y
time drush tome:import -y
