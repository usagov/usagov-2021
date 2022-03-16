<?php

namespace Drupal\tome_static_super_cache\Commands;

use Drupal\tome_base\CommandBase;
use Drupal\tome_static_super_cache\SuperStaticCache;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Contains the tome:super-cache-rebuild command.
 */
class TomeSuperCacheRebuildCommand extends CommandBase {

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this->setName('tome:super-cache-rebuild')
      ->setAliases(['tscr'])
      ->setDescription('Rebuilds cache without conditions.');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $GLOBALS[SuperStaticCache::FULL_REBUILD_KEY] = TRUE;
    drupal_flush_all_caches();
    $this->io()->success('Full cache rebuild complete.');
  }

}
