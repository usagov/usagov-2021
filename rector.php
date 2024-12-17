<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
  $rectorConfig->paths([
    __DIR__ . '/web/modules/custom/',
    __DIR__ . '/web/themes/custom/',
  ]);

  // Other configurations, such as rule sets
  $rectorConfig->sets([
    SetList::PHP_83,
  ]);
};
