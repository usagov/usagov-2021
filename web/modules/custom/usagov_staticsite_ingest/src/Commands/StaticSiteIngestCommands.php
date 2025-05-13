<?php

namespace Drupal\usagov_staticsite_ingest\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Console\Bootstrap\Drupal;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Formatter\OutputFormatter;

/**
 * Class StaticSiteIngestCommands.
 *
 * @package Drupal\usagov_staticsite_ingest\Commands
 */
class StaticSiteIngestCommands extends DrushCommands {

  /**
   * Comment
   *
   * @command \usagov_staticsite_ingest:listCollections
   * @aliases listCollections
   */
  public function listCollections($uuid, $options = ['format' => 'table']) {
    $output_array = [];
    return new RowsOfFields($output_array);
  }

  /**
   * Comment
   *
   * @command \usagov_staticsite_ingest:submitEmbedChunk
   * @aliases submitEmbedChunk
   */
  public function submitEmbedChunk( $collectionName, $chunk, $options = ['format' => 'table']) {
    $output_array = [];
    return new RowsOfFields($output_array);
  }

}
