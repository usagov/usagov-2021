/*
* * * * * ==============================
* * * * * ==============================
* * * * * ==============================
* * * * * ==============================
========================================
========================================
========================================
----------------------------------------
USWDS SASS GULPFILE
----------------------------------------
*/

'use strict';

const sass = require('gulp-sass')(require('sass'));
const autoprefixer = require("autoprefixer");
const csso = require("postcss-csso");
const gulp = require("gulp");
const pkg = require("./node_modules/@uswds/uswds/package.json");
const postcss = require("gulp-postcss");
const replace = require("gulp-replace");
//const sass = require("gulp-sass");
const sourcemaps = require("gulp-sourcemaps");
const uswds = "./node_modules/@uswds/uswds";
const del = require('del');
const svgSprite = require('gulp-svg-sprite');
const rename = require('gulp-rename');
const jsYaml = require('js-yaml');
const fs = require('fs');

const eslint = require('gulp-eslint');
const plumber = require('gulp-plumber');

//sass.compiler = require("sass");

/*
----------------------------------------
PATHS
----------------------------------------
- All paths are relative to the
  project root
- Don't use a trailing `/` for path
  names
----------------------------------------
*/

// Project Sass source directory
const PROJECT_SASS_SRC = "./sass";
const PROJECT_USWDS_SASS_SRC = PROJECT_SASS_SRC + "/uswds";

// Images destination
const IMG_DEST = "./assets/img";

// Fonts destination
const FONTS_DEST = "./fonts";

// Javascript destination
const JS_DEST = "./scripts";

// Compiled CSS destination
const CSS_DEST = "./css";

// Site CSS destination
// Like the _site/assets/css directory in Jekyll, if necessary.
// If using, uncomment line 106
const SITE_CSS_DEST = "./path/to/site/css/destination";

const onError = (err) => {
  console.log(err);
};

/*
----------------------------------------
TASKS
----------------------------------------
*/

gulp.task("copy-uswds-setup", () => {
  return gulp
    .src(`${uswds}/dist/theme/**/**`)
    .pipe(gulp.dest(`${PROJECT_USWDS_SASS_SRC}`));
});

gulp.task("copy-uswds-fonts", () => {
  return gulp.src(`${uswds}/dist/fonts/**/**`).pipe(gulp.dest(`${FONTS_DEST}`));
});

gulp.task("copy-uswds-images", () => {
  return gulp.src(`${uswds}/dist/img/**/**`).pipe(gulp.dest(`${IMG_DEST}`));
});
gulp.task("copy-usagov-images", () => {
  return gulp.src(`./images/**/**`).pipe(gulp.dest(`${IMG_DEST}`));
});

gulp.task("copy-uswds-js", () => {
  return gulp.src(`${uswds}/dist/js/**/**`).pipe(gulp.dest(`${JS_DEST}`));
});

gulp.task("build-sass", function(done) {
  var plugins = [
    // Autoprefix
    autoprefixer({
      cascade: false,
      grid: true
    }),
    // Minify
    csso({ forceMediaMerge: false }),
  ];
  return (
    gulp
      .src([`${PROJECT_SASS_SRC}/*.scss`])
      .pipe(sourcemaps.init({ largeFile: true }))
      .pipe(
        sass({
          includePaths: [
            `${PROJECT_SASS_SRC}`,
            `${uswds}`,
            `${uswds}/packages`
          ]
        })
      )
      .pipe(replace(/\buswds @version\b/g, "based on uswds v" + pkg.version))
      .pipe(postcss(plugins))
      .pipe(sourcemaps.write("."))
      // uncomment the next line if necessary for Jekyll to build properly
      //.pipe(gulp.dest(`${SITE_CSS_DEST}`))
      .pipe(gulp.dest(`${CSS_DEST}`))
  );
});

gulp.task("build-sprite", function (done) {
  const spriteConfig = {
    shape: {
      dimension: { // Set maximum dimensions
        maxWidth: 24,
        maxHeight: 24
      },
      id: {
        separator: "-"
      },
      spacing: { // Add padding
        padding: 0
      }
    },
    mode: {
      symbol: true // Activate the «symbol» mode
    }
  };
  gulp.src(`${IMG_DEST}/usa-icons/**/*.svg`,
  {
    allowEmpty: true
  })
    .pipe(svgSprite(spriteConfig))
    .on('error', function(error) {
      console.log("There was an error");
    })
    .pipe(gulp.dest(`${IMG_DEST}`))
    .on('end', function () { done(); });
});

gulp.task("rename-sprite", function (done) {
  gulp.src(`${IMG_DEST}/symbol/svg/sprite.symbol.svg`,
  {
    allowEmpty: true
  })
    .pipe(rename(`${IMG_DEST}/sprite.svg`))
    .pipe(gulp.dest(`./`))
    .on('end', function () { done(); });
});

gulp.task("clean-sprite", function(cb) {
  cb();
  return del.sync(`${IMG_DEST}/symbol`);
});

gulp.task('clean', (cb) => {
  cb();
  return del.sync([`${CSS_DEST}/*`, `${JS_DEST}/uswds*`]);
});

gulp.task("build", gulp.series(
  "clean",
  "copy-uswds-setup",
  "copy-uswds-fonts",
  "copy-uswds-images",
  "copy-usagov-images",
  "copy-uswds-js",
  "build-sass"
));

gulp.task('js-lint', () => {
  return gulp.src([`${JS_DEST}/*.js`, `!${JS_DEST}/*.min.js`, `!${JS_DEST}/uswds-init.js`, `!${JS_DEST}/uswds.js`])
    .pipe(plumber({errorHandler: onError}))
    .pipe(eslint())
    .pipe(eslint.format('table'));
});

gulp.task('lint', gulp.series("js-lint", "build-sass"));

gulp.task("watch-sass", function() {
  gulp.watch(`${PROJECT_SASS_SRC}/**/*.scss`, gulp.series("build-sass"));
});

gulp.task("watch", gulp.series("build-sass", "watch-sass"));

gulp.task("default", gulp.series("watch"));

gulp.task("svg-sprite", gulp.series("build-sprite", "rename-sprite", "clean-sprite"));

gulp.task("start", gulp.series("build", "watch"));
