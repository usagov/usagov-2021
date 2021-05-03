# Swagger UI for OpenAPI UI Module

The Swagger UI for OpenAPI UI module provides a visual web UI for browsing REST
API documentation using [swagger-ui](https://github.com/swagger-api/swagger-ui).

The swagger-ui library needs to be installed into the drupal libraries folder
for this module to work properly. For sites using composer, the package is found
on Packagist. Otherwise installation can be done manually.

## Installation

### Composer (recommended)

If you're using composer to manage the site (recommended), there are two
composer plugins required for this setup, `composer/installers` and `mnsami/
composer-custom-directory-installer`. `composer-custom-directory-installer`
extends the base installer libraries to allow setting installer path for an
individual package. Follow the steps below.

1. Run the following to ensure that you have the `composer/installers` and
`mnsami/composer-custom-directory-installer` packages installed. These package
facilitate the installation of packages into directories other than `/vendor`
(e.g. `web/libraries`) using Composer.

```
composer require composer/installers mnsami/composer-custom-directory-installer
```

2. Edit your project's composer.json file to include instructions for installing
the `swagger-ui` library to Drupal's `/libraries`. Your file should include the
following:

```
"extra": {
  "installer-paths": {
    ...
    "web/libraries/{$name}": ["swagger-api/swagger-ui", "type:drupal-library"],
    ...
  }
}
```

3. Run the following to add this module to your composer project. 

```
composer require drupal/openapi_ui_swagger
```

### Manual Installation

If you are not managing your project through composer you can manually download
swagger-ui from https://github.com/swagger-api/swagger-ui/archive/v3.0.17.zip,
and extract the files into your `/libraries/swagger-ui` for your Drupal Project.
