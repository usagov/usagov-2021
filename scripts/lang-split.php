<?php

die("\nOne time use script to split spanish lanauge node translations into separate nodes. Comment out line ". (__LINE__) ." to run.\n\nUSAGE: bin/drush scr lang-split.php >> lang-split.log;\n\n");

use Drupal\node\Entity\Node;
use Drupal\menu_link_content\Entity\MenuLinkContent;

$database = \Drupal::database();
$mlcs = \Drupal::entityTypeManager()->getStorage('menu_link_content');

$query = \Drupal::entityQuery('node')
  ->condition('type', ['basic_page'], 'IN')
  ->condition('langcode', 'en');
$en_nids = $query->execute();

echo "EN page count: ". count($en_nids) ."\n\n";

die("\nOne time use script to split spanish lanauge node translations into separate nodes. Comment out line ". (__LINE__) ." to run.\n\nUSAGE: bin/drush scr lang-split.php >> lang-split.log;\n\n");

$nodes_checked = [];
$nodes_updated = [];
$nodes_created = [];
$node_failures = [];
$menu_failures = [];
$menu_transfers = [];

foreach ($en_nids as $en_nid) {
  if($en_node = \Drupal\node\Entity\Node::load($en_nid)) {
    $nodes_checked[] = $en_nid;

    $en_title = $en_node->label();
    if (!$en_node->hasTranslation('es')) {
      echo "EN node ($en_nid) has NO spanish version: $en_title\n";
      continue;
    }

    $translation = $en_node->getTranslation('es');
    $es_title = $translation->label();
    echo "EN node ($en_nid) has a spanish version\n en: $en_title\n es: $es_title\n";

    echo " - Checking for an existing separate ES node ... ";
    $query = \Drupal::entityQuery('node')
      ->condition('title', $es_title)
      ->condition('type', 'basic_page')
      ->condition('nid', $en_nid, '<>')
      ->condition('langcode', 'es');
    $es_nids = $query->execute();
    if ( count($es_nids) > 0 ) {
      echo "found exisitng (". implode(", ", $es_nids) .")\n";
      if ( count($es_nids) > 1 ) {
        echo " ERROR\n - More than one matching ES node found with this title! SKIPPING\n";
        continue;
      }
      $es_nid = array_pop($es_nids);
    } else {
      echo "none exist, we need to create one\n";

      $es_node = Node::create(['type' => 'basic_page']);
      foreach ($translation->getFields() as $name => $field) {
        if ( in_array($name,[
          'type','status','title','created','changed','moderation_state','path','menu_link','body',
          'field_css_icon','field_footer_html',
          'field_for_contact_center_only','field_header_html',
          'field_is_navigation_page','field_page_intro','field_wizard_step'
        ]) ) {
          $es_node->set($name, $field->getValue());
        }
      }
      $es_node->langcode = "es";
      $es_node->set('field_language_toggle', ['target_id' => $en_nid]);

      echo " - ES node being created ... ";
      $es_node->save();

      $es_nid = $es_node->id();
      if ( $es_nid ) {
        $nodes_created[] = $es_nid;
        echo "success new ES node (" . $es_node->id() . ")\n";
      } else {
        $node_failures[] = $en_nid;
        echo "failure\n";
        continue;
      }
    }

    // Checking for menu Links
    $result = $database->query(
      "SELECT 1 as 'found' FROM {menu_link_content_data} WHERE link__uri = :olduri and langcode = 'es'",
      [':olduri' => 'entity:node/' . $en_nid ]
    );
    $existing_menu_links = $result->fetchAssoc();
    if( $existing_menu_links ) {
      echo " - Transfering Menu Links ... ";
      $mlc = $mlcs->loadByProperties(['link__uri' => 'entity:node/' . $en_nid, 'langcode'=>'es']);
      foreach ($mlc as $m) {
        $m->set('link',['uri' => 'entity:node/' . $es_nid]);
        $m->save();

        /// look for the menu item we just tried to update - it should have our new spanish node id
        $result = $database->query("SELECT 1 as 'found' FROM {menu_link_content_data} WHERE link__uri = :newuri and langcode = 'es'", [':newuri' => 'entity:node/'.$es_nid ]);
        $existing_menu_links = $result->fetchAssoc();
        if( $existing_menu_links === FALSE ){
          $menu_failures[] = $en_nid;
          echo "failure\n";
        } else {
          $menu_transfers[] = [ 'en_nid'=>$en_nid, 'es_nid'=>$es_nid, 'mid'=> $m->id() ];
          echo "success\n";
        }
      }

      $translation->menu_link = null;
      $translation->save();
    }

    echo " - Deleting ES translation of original EN node ... ";
    $en_node->removeTranslation('es');
    $en_node->set('field_language_toggle', ['target_id' => $es_nid]);
    $en_node->save();

    // Check for the old spanish translation - it should not exist anymore
    $query = \Drupal::entityQuery('node')
      ->condition('title', $es_title)
      ->condition('type', ['basic_page','wizard'], 'IN')
      ->condition('nid', $en_nid, '=')
      ->condition('langcode', 'es');
    $trans_nids = $query->execute();
    if ( !$trans_nids || !count($trans_nids) ) {
      $nodes_updated[] = $en_nid;
      echo "success\n";
    } else {
      $node_failures[] = $en_nid;
      echo "failure\n";
    }
  }
}

print_r([
  'Nodes Checked' => $nodes_checked,
  'Nodes Updated' => $nodes_updated,
  'Nodes Created' => $nodes_created,
  'Menu Transfers' => $menu_transfers,
  'Node Failures' => $node_failures,
  'Menu Failures' => $menu_failures,
]);
