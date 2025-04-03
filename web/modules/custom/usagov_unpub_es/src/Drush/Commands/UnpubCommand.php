<?php

namespace Drupal\usagov_unpub_es\Drush\Commands;

use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Helper\ProgressBar;

class UnpubCommand extends DrushCommands {

  /**
   * This Drupal command un-publishes all taxonomy content.
   *
   * @command unpubspanish
   */
  public function unpubspanish() {

    $this->unpubSpanishNodes();
    $this->unpubSpanishTaxonomy();
  }

  public function unpubSpanishTaxonomy() {

    // Get all taxonomy term IDs where the language is Spanish.
    $query = \Drupal::entityQuery('taxonomy_term')
      ->condition('langcode', 'es')
      ->condition('status', 1)
      ->accessCheck(FALSE);
    $term_ids = $query->execute();
    if (empty($term_ids)) {
      return;
    }

    // Get the count of entities
    $count = count($term_ids);
    print "Going to unpublish {$count} Spanish taxonomy-terms.\n";

    // Initialize the progress bar
    $progressBar = new ProgressBar($this->output(), $count);
    $progressBar->start();

    $terms = Term::loadMultiple($term_ids);
    foreach ($terms as $term) {
      $term->set('status', 0); // Unpublish the term.
      try {
        $term->save();
      }
      catch (EntityStorageException $e) {
        \Drupal::logger('custom_module')->error('Error unpublishing term ID: @id, Error: @error', [
          '@id' => $term->id(),
          '@error' => $e->getMessage(),
        ]);
      }
      $progressBar->advance();
    }

    $progressBar->finish();
    print "Unpublished {$count} Spanish taxonomy-terms.\n";
  }

  public function unpubSpanishNodes() {

    // Get all Spanish Node-IDs
    $nids = \Drupal::entityQuery('node')->condition('langcode', 'es')->accessCheck(TRUE)->execute();

    // Calculate total, and verbose info
    $total = count($nids);
    print "Going to un-publish {$total} nodes.\n";

    // Initialize the progress bar
    $progressBar = new ProgressBar($this->output(), $total);
    $progressBar->start();

    // Un-publish each node
    $index = 0;
    foreach ($nids as $nid) {

      // Dont unpublish the front page
      if (intval($nid) === 1) {
        continue;
      }

      $node = Node::load($nid);

      $node->setPublished(FALSE);
      $node->setUnpublished();
      $node->set('status', 0);

      if ($node->hasField('moderation_state') && !$node->get('moderation_state')->isEmpty()) {
        $node->set('moderation_state', 'archived');
      }

      $node->save();
      $index++;

      $progressBar->advance();
    }

    $progressBar->finish();
  }

}
