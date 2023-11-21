<?php

namespace Drupal\usagov_benefit_finder_content\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\node\Entity\node;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileInterface;

/**
 * Class CheckDataController
 * @package Drupal\usagov_benefit_finder_content\Controller
 */
class CheckDataController {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The file system service.

   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Thee file repository service.
   *
   * @var \Drupal\file\FileRepositoryInterface
   */
  protected $fileRepository;

  /**
   * The file url generator service.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * Active database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Retrieves the currently active request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The JSON data mode.
   *
   * @var string
   */
  protected $mode;

  /**
   * The langcode.
   *
   * @var string
   */
  protected $langcode;

  /**
   * The expanded.
   *
   * @var string
   */
  protected $expanded;

  /**
   * Constructs a new LifeEventController object.
   */
  public function __construct()
  {
    $this->entityTypeManager = \Drupal::service('entity_type.manager');
    $this->fileSystem = \Drupal::service('file_system');
    $this->fileRepository = \Drupal::service('file.repository');
    $this->fileUrlGenerator = \Drupal::service('file_url_generator');
    $this->database = \Drupal::service('database');
    $this->request = \Drupal::request();
  }

  /**
   * Checks benefit finder data.
   */
  public function checkData() {
    $this->mode = "draft";

    // Get langcode.
    if (empty($this->langcode)) {
      $this->langcode = $this->request->get('langcode') ?? "en";
    }

    // Get expanded.
    if (empty($this->expanded)) {
      $this->expanded = $this->request->get('expanded') ?? "false";
    }

    $help = <<<EOD
<h1>Benefit Finder Content Report</h1>
<pre>
This report provides information of criteria, benefit, life event form.
Query parameter:
langcode: 1) en: English (default) 2) es: Spanish
expanded: 1)false: all accordions closed (default)  2) true: all accordions expanded

Example: /bears/content/report?langcode=en&expanded=true
Generate report of English with all accordions expanded.
</pre>
EOD;

    $result1 = $this->checkCriteria();
    $result2 = $this->checkBenefit();
    $result3 = $this->checkLifeEventForm();
    $result = $help . $result1 . $result2 . $result3;
    $build = [
      '#type' => 'inline_template',
      '#template' => $result,
    ];

    return $build;
  }

  /**
   * Checks criteria.
   */
  public function checkCriteria() {
    $nodes = [];
    $query = \Drupal::entityQuery('node')
      ->accessCheck(TRUE)
      ->condition('type', 'bears_criteria')
      ->condition('langcode', $this->langcode)
      ->sort('field_b_id', 'ASC')
      ->range(0, 1000);
    $nids = $query->execute();
    foreach ($nids as $nid) {
      $node = $this->getNode($nid, $this->mode);

      $vs = $node->get('field_b_values')->getValue();
      $values = [];
      foreach ($vs as $v) {
        $values[] = $v['value'];
      }
      $values = json_encode(array_values($values));
      $nodes[] = [
        "Title" => $node->get('title')->value ?? "",
        "Criteria Key" => $node->get('field_b_criteria_key')->value ?? "",
        "ID" => $node->get('field_b_id')->value ?? "",
        "Name" => $node->get('field_b_name')->value ?? "",
        "Label" => $node->get('field_b_label')->value ?? "",
        "Type" => $node->get('field_b_type')->value ?? "",
        "Has Child" => $node->get('field_b_type')->value ?? "",
        "Child Dependency Option" => $node->get('field_b_child_dependency_option')->value ?? "",
        "values" => $values
      ];
    }

    $html = '';
    $index = 0;
    $expanded = $this->expanded;
    foreach ($nodes as $node) {
      $index += 1;
      $criteria_key = $node["Criteria Key"];
      $x1 = <<<EOD
<div class="usa-accordion">
  <h4 class="usa-accordion__heading">
    <button
      type="button"
      class="usa-accordion__button"
      aria-expanded=$expanded
      aria-controls="c$index"
    >
      $index Criteria Key: $criteria_key
    </button>
  </h4>
  <div id="c$index" class="usa-accordion__content usa-prose">
EOD;
      $x2 = "<pre style='white-space: pre-wrap'>" . print_r($node, true) . "</pre>";
      $x3 = <<<EOD
  </div>
</div>
EOD;
      $html = $html . $x1 . $x2. $x3;
    }

    $html = "<h1>CRITERIA</h1>" . $html;
    return $html;
  }

  /**
   * Checks benefit.
   */
  public function checkBenefit() {
    $nodes = [];
    $query = \Drupal::entityQuery('node')
      ->accessCheck(TRUE)
      ->condition('type', 'bears_benefit')
      ->condition('langcode', $this->langcode)
      ->sort('title', 'ASC')
      ->range(0, 1000);
    $nids = $query->execute();

    foreach ($nids as $nid) {
      $node = $this->getNode($nid, $this->mode);

      // Build benefit.
      $benefit = [
        "title" => $node->get('title')->value,
        "summary" => $node->get('field_b_summary')->value ?? "",
        "SourceLink" => $node->get('field_b_source_link')->value ?? "",
        "SourceIsEnglish" => $node->get('field_b_source_is_english')->value ? "TRUE": "FALSE"
      ];

      // Get agency node and build benefit agency.
      $target_id = $node->get('field_b_agency')->target_id;
      $agency = $this->getAgency($target_id);
      if ($agency) {
        $benefit["agency"] = [
          "title" => $agency->get('title')->value,
          "summary" => $agency->get('field_b_summary')->value ?? "",
          "lede" => $agency->get('field_b_lede')->value ?? ""
        ];
      }
      else {
        $benefit["agency"] = [];
      }

      // Build tags.
      $tags = $node->get('field_b_tags')->referencedEntities();
      foreach ($tags as $tag) {
        $benefit["tags"][] = $tag->get('name')->value;
      }

      // Build life event.
      $lifeEvents = $node->get('field_b_life_events')->getValue();
      foreach ($lifeEvents as $lifeEvent) {
        $service = $this->entityTypeManager->getStorage('node');
        $node1 = $service->load($lifeEvent['target_id']);
        $benefit['lifeEvents'][] = $node1->get('title')->value;
      }

      // Build eligibilities.
      $benefit_eligibilitys = [];
      $eligibilities = $node->get('field_b_eligibility')->referencedEntities();
      foreach ($eligibilities as $eligibility) {
        $benefit_eligibility = [];

        $target_id = $eligibility->get('field_b_criteria_key')->target_id;
        $criteria_node = $this->getCriteria($target_id);
        if ($criteria_node) {
          $ckey = $criteria_node->get('field_b_criteria_key')->value;

          $benefit_eligibility['criteriaKey'] = $ckey;
          $benefit_eligibility['label'] = $eligibility->get('field_b_label')->value ?? "";

          $acceptableValues = $eligibility->get('field_b_acceptable_values')->getValue();
          foreach ($acceptableValues as $key => $acceptableValue) {
            $benefit_eligibility['acceptableValues'][] = $acceptableValue['value'];
          }

          $benefit_eligibilitys[] = $benefit_eligibility;
        }

      }

      $benefit['eligibility'] = $benefit_eligibilitys;

      $nodes[] = $benefit;
    }

    $html = '';
    $index = 0;
    $expanded = $this->expanded;
    foreach ($nodes as $node) {
      $index += 1;
      $title = $node["title"];
      $x1 = <<<EOD
<div class="usa-accordion">
  <h4 class="usa-accordion__heading">
    <button
      type="button"
      class="usa-accordion__button"
      aria-expanded=$expanded
      aria-controls="b$index"
    >
      $index Title: $title
    </button>
  </h4>
  <div id="b$index" class="usa-accordion__content usa-prose">
EOD;
      $x2 = "<pre style='white-space: pre-wrap'>" . print_r($node, true) . "</pre>";
      $x3 = <<<EOD
  </div>
</div>
EOD;
      $html = $html . $x1 . $x2. $x3;
    }

    $html = "<h1>BENEFIT</h1>" . $html;
    return $html;
  }

  /**
   * Checks life event form.
   */
  public function checkLifeEventForm() {
    $nodes = [];
    $query = \Drupal::entityQuery('node')
      ->accessCheck(TRUE)
      ->condition('type', 'bears_life_event_form')
      ->condition('langcode', $this->langcode)
      ->sort('field_b_id', 'ASC')
      ->range(0, 1000);
    $nids = $query->execute();

    foreach ($nids as $nid) {
      $life_event_form_node = $this->getNode($nid, $this->mode);
      $life_event_form_node_id = $life_event_form_node->id();

      // Build life event form.
      $life_event_form = [
        "id" => $life_event_form_node->get('field_b_id')->value,
        "timeEstimate" => $life_event_form_node->get('field_b_time_estimate')->value ?? "",
        "titlePrefix" => $life_event_form_node->get('field_b_title_prefix')->value ?? "",
        "title" => $life_event_form_node->get('title')->value ?? "",
        "summary" => $life_event_form_node->get('field_b_summary')->value ?? ""
      ];

      // Get Relevant Benefits.
      $relevant_benefits = $life_event_form_node->get('field_b_relevant_benefits')->referencedEntities();

      // Build Relevant Benefits.
      $life_event_form_relevant_benefits = [];
      foreach ($relevant_benefits as $relevant_benefit) {
        $life_event_form_relevant_benefit = [
          "title" => current($relevant_benefit->get('field_b_life_event_form')->referencedEntities())->get('title')->value ?? "",
          "body" => $relevant_benefit->get('field_b_body')->value ?? "",
          "link" => $relevant_benefit->get('field_b_link')->value ?? "",
          "cta" => $relevant_benefit->get('field_b_cta')->value ?? ""
        ];
        $life_event_form_relevant_benefits[]['lifeEvent'] = $life_event_form_relevant_benefit;
      }
      $life_event_form['relevantBenefits'] = $life_event_form_relevant_benefits;

      // Get Sections of Eligibility Criteria.
      $sections = $life_event_form_node->get('field_b_sections_elg_criteria')->referencedEntities();

      // Build sections of eligibility criteria.
      $life_event_form_sections = [];

      foreach ($sections as $section) {
        $life_event_form_section = [
          "heading" => $section->get('field_b_heading')->value ?? "",
          "description" => $section->get('field_b_description')->value ?? ""
        ];

        // Get criterias of a section.
        $criterias = $section->get('field_b_criterias')->referencedEntities();

        // Build criteria fieldsets.
        $criteria_fieldsets = [];
        foreach ($criterias as $criteria) {
          $criteria_fieldset = [];
          if ($criteria->type->target_id == "b_levent_elg_criteria") {
            $criteria_fieldset = $this->buildCriteriaFieldset($criteria);
          } else if ($criteria->type->target_id == "b_levent_elg_criteria_group") {
            $criteria_fieldset = $this->buildCriteriaGroupFieldset($criteria);
          }
          $criteria_fieldsets[]['fieldset'] = $criteria_fieldset;
        }

        $life_event_form_section['fieldsets'] = $criteria_fieldsets;
        $life_event_form_sections[]['section'] = $life_event_form_section;
      }

      $life_event_form['sectionsEligibilityCriteria'] = $life_event_form_sections;

      $nodes[] = $life_event_form;
    }

    $html = '';
    $index = 0;
    $expanded = $this->expanded;
    foreach ($nodes as $node) {
      $index += 1;
      $id = $node["id"];
      $title = $node["title"];
      $x1 = <<<EOD
<div class="usa-accordion">
  <h4 class="usa-accordion__heading">
    <button
      type="button"
      class="usa-accordion__button"
      aria-expanded=$expanded
      aria-controls="l$index"
    >
      $index ID: $id | Title: $title
    </button>
  </h4>
  <div id="l$index" class="usa-accordion__content usa-prose">
EOD;
      $x2 = "<pre>" . print_r($node, true) . "</pre>";
      $x3 = <<<EOD
  </div>
</div>
EOD;
      $html = $html . $x1 . $x2. $x3;
    }

    $html = "<h1>LIFE EVENT FORM</h1>" . $html;
    return $html;
  }

  /**
   * Builds criteria group fieldset.
   * @param $criteria
   * @return array
   */
  public function buildCriteriaGroupFieldset($criteria) {
    $criteria_group_fieldset = [];

    // Build criteria group fieldset.
    $criteria_group_fieldset = [
      "heading" => $criteria->get("field_b_heading")->value ?? "",
      "description" => $criteria->field_b_description->value ?? ""
    ];

    // Get criterias multi paragraphs.
    $criterias = $criteria->get('field_b_criterias')->referencedEntities();

    // Build criteria group criteria fieldsets.
    $group_fieldsets = [];
    foreach ($criterias as $criteria) {
      $criteria_fieldset = $this->buildCriteriaFieldset($criteria);
      $group_fieldsets[]['fieldset'] = $criteria_fieldset;
    }

    $criteria_group_fieldset["fieldsets"] = $group_fieldsets;

    return $criteria_group_fieldset;
  }

  /**
   * Builds criteria fieldset.
   * @param $criteria
   * @return array
   */
  public function buildCriteriaFieldset($criteria)
  {
    $criteria_fieldset = [];

    // Get criteria node.
    $target_id = $criteria->get('field_b_criteria_key')->target_id;
    $criteria_node = $this->getCriteria($target_id);

    // Do not build missing criteria.
    if (empty($criteria_node)) {
      return $criteria_fieldset;
    }

    // Build criteria fieldset.
    $criteria_fieldset = [
      "criteriaKey" => current($criteria->get('field_b_criteria_key')->referencedEntities())->get('field_b_id')->value,
      "legend" => $criteria->get('field_b_legend')->value ?? "",
      "required" => $criteria->get('field_b_required')->value ? "TRUE":"FALSE",
      "hint" => $criteria->get('field_b_hint')->value ?? ""
    ];

    // Build inputCriteria.
    $inputCriteria = [
      "id" => $criteria_node->get('field_b_id')->value,
      "type" => $criteria_node->get('field_b_type')->value,
      "name" => $criteria_node->get('field_b_name')->value ?? "",
      "label" => $criteria_node->get('field_b_label')->value ?? "",
      "hasChild" => $criteria_node->get('field_b_has_child')->value ? "TRUE":"FALSE",
      "childDependencyOption" => $criteria_node->get('field_b_child_dependency_option')->value ?? ""
    ];

    $criteria_values = [];

    if ($criteria_node->get('field_b_type')->value == 'date' || $criteria_node->get('field_b_type')->value == "Date") {
      $criteria_values[] = array(
        "default" => "",
        "value" => (object)[]
      );
    }

    $b_values = $criteria_node->get('field_b_values')->getValue();
    foreach ($b_values as $b_value) {
      $criteria_values[] = array(
        "option" => $b_value["value"],
        "value" => $b_value["value"]
      );
    }
    $inputCriteria["values"] = $criteria_values;

    $criteria_fieldset["inputs"][]["inputCriteria"] = $inputCriteria;

    // Get criterias fieldsets multi paragraphs
    $criterias_1 = $criteria->get('field_b_children')->referencedEntities();
    if (empty($criterias_1)) {
      $criteria_fieldset["children"] = [];
    }
    else {
      foreach ($criterias_1 as $criteria_1) {
        $criteria_fieldset_1 = [];
        if ($criteria_1->type->target_id == "b_levent_elg_criteria") {
          $criteria_fieldset_1 = $this->buildCriteriaFieldset($criteria_1);
        } else if ($criteria_1->type->target_id == "b_levent_elg_criteria_group") {
          $criteria_fieldset_1 = $this->buildCriteriaGroupFieldset($criteria_1);
        }
        $criteria_fieldset["children"][]["fieldsets"][]['fieldset'] = $criteria_fieldset_1;
      }
    }

    return $criteria_fieldset;
  }

  /**
   * Gets criteria of given nid.
   *
   * @param $nid
   *   The criteria node ID.
   * @return \Drupal\node\NodeInterface
   *   The criteria node.
   */
  public function getCriteria($nid) {
    return $this->getNode($nid, $this->mode);
  }

  /**
   * Gets agency of given nid.
   *
   * @param $nid
   *   The agency node ID.
   * @return \Drupal\node\NodeInterface
   *   The agency node.
   */
  public function getAgency($nid) {
    return $this->getNode($nid, $this->mode);
  }

  /**
   * Gets node of given nid and mode.
   *
   * @param $nid
   *   The node ID.
   * @param $mode
   *   The mode.
   * @return \Drupal\node\NodeInterface
   *   The node.
   */
  public function getNode($nid, $mode) {
    $vid = 0;

    // Do not use node of moderation state archived.
    $id = $this->database
      ->query('SELECT id FROM content_moderation_state_field_data
                        WHERE moderation_state = :mstate AND content_entity_id = :nid',
                        [':mstate' => 'archived', ':nid' => $nid])
      ->fetchField();
    if ($id) {
      return NULL;
    }

    if ($mode == "published") {
      $vid = $this->database
        ->query('SELECT MAX(vid) AS vid FROM node_field_revision WHERE status = 1 AND nid = :nid', [':nid' => $nid])
        ->fetchField();
    }
    else if ($mode == "draft") {
      $vid = $this->database
        ->query('SELECT MAX(vid) AS vid FROM node_field_revision WHERE nid = :nid', [':nid' => $nid])
        ->fetchField();
    }
    else {
      // @todo Unknown
    }

    if ($vid) {
      $node = node_revision_load($vid);
    }
    else {
      $node = NULL;
    }

    return $node;
  }

}
