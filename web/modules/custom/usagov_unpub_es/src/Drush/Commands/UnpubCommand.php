<?php

namespace Drupal\usagov_unpub_es\Drush\Commands;

use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drush\Commands\DrushCommands;

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

    $count = count($terms);
    print "Going to unpublish {$count} Spanish taxonomy-terms.\n";

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
    }

    print "Unpublished {$count} Spanish taxonomy-terms.\n";
  }

  public function unpubSpanishNodes() {

    // Get all Spanish Node-IDs
    $database = Database::getConnection();
    $query = $database->select('node_field_data', 'n');
    $query->fields('n', ['nid']);
    $query->condition('n.langcode', 'es', '=');
    $nids = $query->execute()->fetchCol();

    // Calculate total, and verbose info
    $total = count($nids);
    print "Going to un-publish {$total} nodes.\n";

    // Un-publish each node
    $index = 0;
    foreach ($nids as $nid) {

      $node = Node::load($nid);

      if ($node && $node->isPublished()) {
        $node->setPublished(FALSE);
      }

      if ($node->hasField('moderation_state') && !$node->get('moderation_state')->isEmpty()) {
        $node->set('moderation_state', 'archived');
      }

      $node->save();
      $index++;

      print "Un-published node {$index} of {$total} \r";
    }

  }

}
