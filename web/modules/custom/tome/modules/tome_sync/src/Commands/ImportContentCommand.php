<?php

namespace Drupal\tome_sync\Commands;

use Drupal\tome_sync\TomeSyncHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Contains the tome:import-content command.
 *
 * @internal
 */
class ImportContentCommand extends ImportCommand {

  /**
   * {@inheritdoc}
   */
  protected  function configure() {
    $this->setName('tome:import-content')
      ->setDescription('Imports given content.')
      ->addArgument('names', InputArgument::REQUIRED, 'A comma separated list of IDs in the format entity_type_id:uuid:langcode.');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $names = $input->getArgument('names');
    $names = explode(',', $names);
    foreach ($names as $name) {
      list($entity_type_id, $uuid, $langcode) = TomeSyncHelper::getPartsFromContentName($name);
      $this->importer->importContent($entity_type_id, $uuid, $langcode);
    }
  }

}
