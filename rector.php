<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
  $rectorConfig->paths([
    __DIR__ . '/web/modules/custom/',
    __DIR__ . '/web/themes/custom/',
  ]);

  $rectorConfig->skip([
    __DIR__ . '/web/themes/custom/usagov/node_modules',
    // These two define the same functions into global scope and confuse
    // static analysis; excluded from phpstan.neon for the same reason.
    __DIR__ . '/web/modules/custom/usagov_directories/utility/states_import_prep.php',
    __DIR__ . '/web/modules/custom/usagov_directories/utility/agency_import_prep.php',
  ]);

  $rectorConfig->sets([
    SetList::PHP_83,
  ]);

  // KNOWN BROKEN (pre-existing, not caused by the D11 upgrade work).
  //
  // This config as committed fails on 46 of the custom files with
  //   Call to undefined method
  //   PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode::getSealedTagValues()
  // Cause: slevomat/coding-standard 8.16.2 requires phpstan/phpdoc-parser
  // ^2.1.0, so an UNSCOPED phpdoc-parser 2.3.3 sits in vendor/ and collides
  // with the SCOPED phpstan that rector/rector 2.0.19 bundles. Any file whose
  // docblocks exercise the newer parser API trips it.
  //
  // Fixing it means realigning rector / phpstan / phpdoc-parser versions,
  // which risks the phpstan.neon baseline that IS working. Tracked as its own
  // ticket rather than folded into the upgrade. Static analysis for the D11
  // work runs through phpstan.neon.
  //
  // palantirnet/drupal-rector (the Drupal 10/11 deprecation sets) was tried
  // and removed: 1.1.3 is the first release supporting rector ^2, but its
  // config/drupal-bootstrap.php calls phpstanConfigs([...phpstan-drupal...]),
  // which compounds the same conflict; neutralising it only moves the failure
  // to "Cannot suspend outside of a fiber".
  //
  // When run, use the cms container (PHP 8.3), NOT composer (PHP 8.5) --
  // rector 2.0.19's parser also fails outright on 8.5 with T_CLONE errors:
  //   docker compose run --rm --no-deps --workdir /var/www --entrypoint="" \
  //     cms vendor/bin/rector process --dry-run
};
