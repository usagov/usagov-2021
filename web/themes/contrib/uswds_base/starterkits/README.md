INTRODUCTION
------------
This sub theme uses uswds-gulp to compile assets.


INSTALLATION
------------

1. Copy this directory to themes/custom and replace theme name in files and code with your own.
2. Install uswds-gulp within the new theme directory via instructions: https://github.com/uswds/uswds-gulp.
    * Use /css for css path in gulpfile.js (used in libraries.yml).
    * Use /assets/js for js path (used in libraries.yml).
    * Use /assets/img for images path (used in uswds_base_preprocess()).
    * Use /assets/* for sass, fonts.
3. Run [gulp init] to create directories and copy files.
    * Change $theme-image-path to ../assets/img in assets/sass/_uswds-theme-general.scss
4. Run [gulp] to build and watch sass.
