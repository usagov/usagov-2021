The process for building the app will split responsibility between two containers
The first container will be responsible for building the theme, and needs to include node and gulp
The second container will be responsible for building drupal, and needs to include drush and composer
The second container will also server as the deployable production version. We want drush on the final deployed version, and are willing to accept the overhead of composer to keep things simple.

The first container (Theme Builder) will only ever be used locally to generate files used to support building out a new deployment. This will keep the node app from having to be installed on the cms directly. 

During builds the first container will generate files that will be imported into the seconds container