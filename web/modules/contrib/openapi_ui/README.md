# OpenAPI UI

The OpenAPI UI module implements an API around displaying OpenAPI specs inside a
Drupal site. This library implements a plugin base which can be used to
initialize a API explorer UI within your site. This module does not come with
any implemented plugins, but implementations for [ReDoc](https://github.com/Rebilly/ReDoc)
and [Swagger UI](https://github.com/swagger-api/swagger-ui) are available as
Drupal modules. Please visit the [Swagger for OpenAPI UI module]
(https://www.drupal.org/project/openapi_ui_swagger) and[ReDoc for OpenAPI UI module]
(https://www.drupal.org/project/openapi_ui_redoc) pages for information about
using those libraries.

Whats OpenAPI (A.K.A. Swagger)?
-------------------------------

[OpenAPI](https://www.openapis.org/) is a specification for documenting web
service apis, that are consistent and reusable by developers. Using a consistent
format allows for the reuse of api client libraries and a consistent experience
for learning a new api. This module fits into the OpenAPI ecosystem by providing
integrations between Drupal and libraries for displaying the api spec for use by
both developers and end users. For information about OpenAPI specifications for
Drupal's apis and the API's for other contributed modules, take a look at the
[OpenAPI module](https://www.drupal.org/project/openapi).

Supported Libraries
-------------------

*   [Swagger UI](https://github.com/swagger-api/swagger-ui) using the [Swagger
    for OpenAPI UI](https://www.drupal.org/project/openapi_ui_swagger) module
*   [ReDoc](https://github.com/Rebilly/ReDoc) using the [ReDoc for OpenAPI UI]
    (https://www.drupal.org/project/openapi_ui_redoc) module

Related Projects
----------------

*   [OpenAPI](https://www.drupal.org/project/openapi) \- Provides OpenAPI/
    Swagger documentation for Drupal Core and contributed modules
*   [Swagger UI Field Formatter](https://www.drupal.org/project/swagger_ui_formatter) -
    Provides a field formatter to display a the contents of files fields using
    the Swagger UI interface.
