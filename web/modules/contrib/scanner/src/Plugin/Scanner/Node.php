<?php

namespace Drupal\scanner\Plugin\Scanner;

use Drupal\scanner\Plugin\ScannerEntityPluginBase;
use Drupal\scanner\Plugin\Scanner\Entity;
use Drupal\scanner\AdminHelper;

/**
 * Class Node.
 *
 * @Scanner(
 *   id = "scanner_node",
 *   type = "node",
 * )
 */
class Node extends Entity {

  /**
   * {@inheritdoc}
   */
  public function search($field, $values) {
    $title_collect = []; 
    // $field will be string composed of entity type, bundle name, and field
    // name delimited by ':' characters.
    list($entityType, $bundle, $fieldname) = explode(':', $field);

    $query = \Drupal::entityQuery($entityType);
    $query->condition('type', $bundle, '=');
    if ($values['published']) {
      $query->condition('status', 1);
    }
    $conditionVals = parent::buildCondition($values['search'], $values['mode'], $values['wholeword'], $values['regex'], $values['preceded'], $values['followed']);
    if ($values['language'] !== 'all') {
      $query->condition('langcode', $values['language'], '=');
      $query->condition($fieldname, $conditionVals['condition'], $conditionVals['operator'], $values['language']);
    }
    else {
      $query->condition($fieldname, $conditionVals['condition'], $conditionVals['operator']);
    }
    
    $entities = $query->execute();
    // Iterate over matched entities (nodes) to extract information that will
    // be rendered in the results.
    foreach ($entities as $key => $id) {
      $node = \Drupal\node\Entity\Node::load($id);
      $type = $node->getType();
      $nodeField = $node->get($fieldname);
      $fieldType = $nodeField->getFieldDefinition()->getType();
      if (in_array($fieldType, ['text_with_summary','text','text_long'])) {
        $fieldValue = $nodeField->getValue()[0];
        $title_collect[$id]['title'] = $node->getTitle();
        // Find all instances of the term we're looking for.
        preg_match_all($conditionVals['phpRegex'], $fieldValue['value'], $matches,PREG_OFFSET_CAPTURE);
        $newValues = [];
        // Build an array of strings which are displayed in the results.
        foreach ($matches[0] as $k => $v) {
          // The offset of the matched term(s) in the field's text.
          $start = $v[1];
          if ($values['preceded'] !== '') {
            // Bolding won't work if starting position is in the middle of a
            // word (non-word bounded searches), therefore move the start
            // position back as many character as there are in the 'preceded'
            // text
            $start -= strlen($values['preceded']);
          }
          // Extract part of the text which include the search term plus six
          // "words" following it. After finding the string, bold the search
          // term.
          $replaced = preg_replace($conditionVals['phpRegex'], "<strong>$v[0]</strong>", preg_split("/\s+/", substr($fieldValue['value'], $start), 6));
          if (count($replaced) > 1) {
            // The final index contains the remainder of the text, which we
            // don't care about so we discard it.
            array_pop($replaced);
          }
          $newValues[] = implode(' ', $replaced);
        }
        $title_collect[$id]['field'] = $newValues;
      }
      elseif ($fieldType == 'string') {
        $title_collect[$id]['title'] = $node->getTitle();
        preg_match($conditionVals['phpRegex'], $nodeField->getString(), $matches, PREG_OFFSET_CAPTURE);
        $match = $matches[0][0];
        $replaced = preg_replace($conditionVals['phpRegex'], "<strong>$match</strong>", $nodeField->getString());
        $title_collect[$id]['field'] = [$replaced];
      }   
    }
    return $title_collect;
  }

  /**
   * {@inheritdoc}
   */
  public function replace($field, $values, $undo_data) {
    $data = $undo_data;
    if (!is_array($data)) {
      $data=[];
    }
    list($entityType, $bundle, $fieldname) = explode(':', $field);

    $query = \Drupal::entityQuery($entityType);
    $query->condition('type', $bundle);
    if ($values['published']) {
      $query->condition('status', 1);
    }
    $conditionVals = parent::buildCondition($values['search'], $values['mode'], $values['wholeword'], $values['regex'], $values['preceded'], $values['followed']);
    if ($values['language'] !== 'all') {
      $query->condition($fieldname, $conditionVals['condition'], $conditionVals['operator'], $values['language']);
    }
    else {
      $query->condition($fieldname, $conditionVals['condition'], $conditionVals['operator']);
    }
    $entities = $query->execute();

    foreach ($entities as $key => $id) {
      $node = \Drupal\node\Entity\Node::load($id);
      $nodeField = $node->get($fieldname);
      $fieldType = $nodeField->getFieldDefinition()->getType();
      if (in_array($fieldType, ['text_with_summary','text','text_long'])) {
        if ($values['language'] === 'all') {
          $other_languages = AdminHelper::getAllEnabledLanguages();
          foreach ($other_languages as $langcode => $languageName) {
            if ($node->hasTranslation($langcode)) {
              $node = $node->getTranslation($langcode);
              $nodeField = $node->get($fieldname);
            }
            $fieldValue = $nodeField->getValue()[0];
            // Replace the search term with the replace term.
            $fieldValue['value'] = preg_replace($conditionVals['phpRegex'], $values['replace'], $fieldValue['value']);
            $node->$fieldname = $fieldValue;
          }
          // This check prevents the creation of multiple revisions if more than
          // one field of the same node has been modified.
          if (!isset($data["node:$id"]['new_vid'])) {
            $data["node:$id"]['old_vid'] = $node->vid->getString();
            // Crete a new revision so that we can have the option of undoing it
            // later on.
            $node->setNewRevision(true);
            $node->revision_log = t('Replaced %search with %replace via Scanner Search and Replace module.', ['%search' => $values['search'], '%replace' => $values['replace']]);
          }
        }
        else {
          $requested_lang = $values['language'];
          if ($node->hasTranslation($requested_lang)) {
            $node = $node->getTranslation($requested_lang);
            $nodeField = $node->get($fieldname);
          }
          $fieldValue = $nodeField->getValue()[0];
          // Replace the search term with the replace term.
          $fieldValue['value'] = preg_replace($conditionVals['phpRegex'], $values['replace'], $fieldValue['value']);
          $node->$fieldname = $fieldValue;
          // This check prevents the creation of multiple revisions if more than
          // one field of the same node has been modified.
          if (!isset($data["node:$id"]['new_vid'])) {
            $data["node:$id"]['old_vid'] = $node->vid->getString();
            // Crete a new revision so that we can have the option of undoing it
            // later on.
            $node->setNewRevision(true);
            $node->revision_log = t('Replaced %search with %replace via Scanner Search and Replace module.', ['%search' => $values['search'], '%replace' => $values['replace']]);
          }
        }
        // Save the updated node.
        $node->save();
        // Fetch the new revision id.
        $data["node:$id"]['new_vid'] = $node->vid->getString();
      }
      elseif ($fieldType == 'string') {
        if (!isset($data["node:$id"]['new_vid'])) {
          if ($values['language'] === 'all') {
            $all_languages = AdminHelper::getAllEnabledLanguages();
            foreach ($all_languages as $langcode => $languageName) {
              if ($node->hasTranslation($langcode)) {
                $node = $node->getTranslation($langcode);
                $nodeField = $node->get($fieldname);
              }
              $fieldValue = preg_replace($conditionVals['phpRegex'], $values['replace'], $nodeField->getString());
              $node->$fieldname = $fieldValue;
            }
            $data["node:$id"]['old_vid'] = $node->vid->getString();
            $node->setNewRevision(true);
            $node->revision_log = t('Replaced %search with %replace via Scanner Search and Replace module.', ['%search' => $values['search'], '%replace' => $values['replace']]);
          }
          else {
            $requested_lang = $values['language'];
            if ($node->hasTranslation($requested_lang)) {
              //$nodeField = $nodeField->getTranslation($requested_lang);
              $node = $node->getTranslation($requested_lang);
              $nodeField = $node->get($fieldname);
            }
            $fieldValue = preg_replace($conditionVals['phpRegex'], $values['replace'], $nodeField->getString());
            $node->$fieldname = $fieldValue;
            $data["node:$id"]['old_vid'] = $node->vid->getString();
            $node->setNewRevision(true);
            $node->revision_log = t('Replaced %search with %replace via Scanner Search and Replace module.', ['%search' => $values['search'], '%replace' => $values['replace']]);
          }
        }
        $node->save();
        $data["node:$id"]['new_vid'] = $node->vid->getString();
      }
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function undo($data) {
    $revision = \Drupal::entityTypeManager()->getStorage('node')->loadRevision($data['old_vid']);
    $revision->setNewRevision(true);
    $revision->revision_log = t('Copy of the revision from %date via Search and Replace Undo', ['%date' => \Drupal::service('date.formatter')->format($revision->getRevisionCreationTime())]);
    $revision->isDefaultRevision(true);
    $revision->save(); 
  }

}
