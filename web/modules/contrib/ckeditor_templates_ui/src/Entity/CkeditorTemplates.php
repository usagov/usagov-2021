<?php

namespace Drupal\ckeditor_templates_ui\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\ckeditor_templates_ui\CkeditorTemplatesUiInterface;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Defines the ckeditor_template entity.
 *
 * @ConfigEntityType(
 *   id = "ckeditor_template",
 *   label = @Translation("Ckeditor Template"),
 *   handlers = {
 *     "list_builder" = "Drupal\ckeditor_templates_ui\Controller\CkeditorTemplateListBuilder",
 *     "form" = {
 *       "default" = "Drupal\ckeditor_templates_ui\Form\CkeditorTemplateForm",
 *       "add" = "Drupal\ckeditor_templates_ui\Form\CkeditorTemplateForm",
 *       "edit" = "Drupal\ckeditor_templates_ui\Form\CkeditorTemplateForm",
 *       "delete" = "Drupal\ckeditor_templates_ui\Form\CkeditorTemplateDeleteForm",
 *     }
 *   },
 *   config_prefix = "ckeditor_template",
 *   admin_permission = "administer ckeditor templates",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "description" = "description",
 *     "html" = "html",
 *     "image" = "image",
 *     "weight" = "weight"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "html",
 *     "image",
 *     "weight"
 *   },
 *   links = {
 *     "add-form" = "/admin/config/content/ckeditor_templates/add",
 *     "edit-form" = "/admin/config/content/ckeditor_templates/{ckeditor_template}",
 *     "delete-form" = "/admin/config/content/ckeditor_templates/{ckeditor_template}/delete",
 *   }
 * )
 */
class CkeditorTemplates extends ConfigEntityBase implements CkeditorTemplatesUiInterface {

  /**
   * The Template ID.
   *
   * @var string
   */
  public $id;

  /**
   * The Template label.
   *
   * @var string
   */
  public $label;

  /**
   * The weight of this template in administrative listings.
   *
   * @var int
   */
  public $weight;

  /**
   * Get Teamplate description.
   *
   * @var string
   */
  public function getDescription() {
    if (isset($this->description)) {
      return $this->description;
    }
    return '';
  }

  /**
   * Get Teamplate html.
   *
   * @var string
   */
  public function getHtml() {
    if (isset($this->html)) {
      return $this->html;
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public static function postLoad(EntityStorageInterface $storage, array &$entities) {
    parent::postLoad($storage, $entities);
    // Sort the queried templates by their weight.
    uasort($entities, 'static::sort');
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    if (!isset($this->weight) && ($templates = $storage->loadMultiple())) {
      // Set a template weight to make this new template last.
      $max = array_reduce($templates, function ($max, $template) {
        return $max > $template->weight ? $max : $template->weight;
      });
      $this->weight = $max + 1;
    }
  }

}
