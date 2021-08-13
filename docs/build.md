The process for building the app will split responsibility between two containers
The first container will be responsible for building drupal, and needs to include drush and composer
The seconds container will be responsible for building the theme, and needs to include node and gulp
The first container will also server as the deployable production version. We want drush on the final deployed version, and are willing to accept the overhead of composer to keep things simple.

