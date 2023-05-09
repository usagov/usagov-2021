const uswds = require("@uswds/compile");

/**
 * USWDS version
 */

uswds.settings.version = 3;

/**
 * Path settings
 * Set as many as you need
 */

uswds.paths.dist.css = './css';
uswds.paths.dist.fonts = './fonts';
uswds.paths.dist.img = './assets/img';
uswds.paths.dist.js = './scripts';

// uswds.paths.dist.theme = './sass';

/**
 * Exports
 * Add as many as you need
 */

exports.init = uswds.init;
exports.compile = uswds.compile;
exports.compileSass = uswds.compileSass;
exports.update = uswds.updateUswds;
exports.watch = uswds.watch;
