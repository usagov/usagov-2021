<?php

namespace Drupal\tome_sync\Commands;

use Drupal\Core\Config\ConfigInstallerInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\tome_sync\ImporterInterface;

/**
 * Contains the tome:delete-content command.
 *
 * @internal
 */
class DeleteContentCommand extends ImportCommand {

  /**
   * The config installer.
   *
   * @var \Drupal\Core\Config\ConfigInstallerInterface
   */
  protected $configInstaller;

  /**
   * Constructs an DeleteContentCommand instance.
   *
   * @param \Drupal\tome_sync\ImporterInterface $importer
   *   The importer.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state system.
   * @param \Drupal\Core\Config\ConfigInstallerInterface $config_installer
   *   The config installer.
   */
  public function __construct(ImporterInterface $importer, EntityTypeManagerInterface $entity_type_manager, StateInterface $state, ConfigInstallerInterface $config_installer) {
    parent::__construct($importer, $entity_type_manager, $state);
    $this->configInstaller = $config_installer;
  }

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this->setName('tome:delete-content')
      ->setDescription('Deletes or removes translations for the given content.')
      ->addArgument('chunk', InputArgument::REQUIRED, 'A comma separated list of content names in the format entity_type_id:id or entity_type_id:id:langcode.');
  }

  /**
   * {@inheritdoc}
   */
  public function execute(InputInterface $input, OutputInterface $output) {
    $this->configInstaller->setSyncing(TRUE);
    $this->importer->isImporting(TRUE);

    $chunk = $input->getArgument('chunk');
    $names = explode(',', $chunk);
    $storages = [];
    foreach ($names as $name) {
      $parts = explode(':', $name);
      $entity_type_id = $parts[0];
      $id = $parts[1];
      $langcode = isset($parts[2]) ? $parts[2] : NULL;
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      if (!$entity_type) {
        $this->io()->error("The entity type $entity_type_id does not exist.");
        return 1;
      }
      if (!isset($storages[$entity_type_id])) {
        $storages[$entity_type_id] = $this->entityTypeManager->getStorage($entity_type_id);
      }
      $entity = $storages[$entity_type_id]->load($id);
      if (!$entity) {
        $this->io()->error("No entity found for $name.");
        return 1;
      }
      if (!($entity instanceof ContentEntityInterface)) {
        $this->io()->error("$name is not a content entity.");
        return 1;
      }
      if ($langcode) {
        if ($translation = $entity->getTranslation($langcode)) {
          $entity->removeTranslation($langcode);
          $entity->save();
        }
        else {
          $this->io()->error("There is no $langcode translation for $name.");
          return 1;
        }
      }
      else {
        $entity->delete();
      }
    }

    $this->importer->isImporting(FALSE);
    $this->configInstaller->setSyncing(FALSE);
  }

}
