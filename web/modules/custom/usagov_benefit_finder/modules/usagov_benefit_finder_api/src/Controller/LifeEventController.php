<?php

namespace Drupal\usagov_benefit_finder_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\usagov_benefit_finder\Traits\BenefitFinderTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class LifeEventController
 * @package Drupal\usagov_benefit_finder_api\Controller
 */
class LifeEventController extends ControllerBase {

  use BenefitFinderTrait;

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
   * The file repository service.
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
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The display data control variable.
   *
   * @var string
   */
  protected $displayData;

  /**
   * The benefit finder content mode.
   *
   * @var string
   */
  public $mode;

  /**
   * Constructs a new LifeEventController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\file\FileRepositoryInterface|null $file_repository
   *   The file repository.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator
   *   The file URL generator.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    FileSystemInterface $file_system,
    FileRepositoryInterface $file_repository,
    FileUrlGeneratorInterface $file_url_generator,
    Connection $database,
    RequestStack $request_stack,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->fileSystem = $file_system;
    $this->fileRepository = $file_repository;
    $this->fileUrlGenerator = $file_url_generator;
    $this->database = $database;
    $this->requestStack = $request_stack;
    $this->displayData = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('file_system'),
      $container->get('file.repository'),
      $container->get('file_url_generator'),
      $container->get('database'),
      $container->get('request_stack'),
    );
  }

  /**
   * Saves JSON data file.
   *
   * @param $id
   *  The life event ID.
   * @return JsonResponse
   *  The response.
   * @throws EntityStorageException
   */
  public function saveJsonData($id) {
    // Get JSON data mode.
    if (empty($this->mode)) {
      $this->mode = $this->requestStack->getCurrentRequest()->query->get('mode') ?? "published";
    }

    // Prepare directory.
    if ($this->mode == "published") {
      $directory = "public://benefit-finder/api/life-event";
    }
    elseif ($this->mode == "draft") {
      $directory = "public://benefit-finder/api/draft/life-event";
    }

    $this->fileSystem->prepareDirectory($directory, FileSystemInterface:: CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    // Get JSON data.
    $this->displayData = FALSE;
    $data = json_encode([
      'data' => $this->getData($id),
      'method' => 'GET',
      'status' => 200,
    ]
    );

    // Write JSON data file.
    $filename = "$directory/$id.json";
    $this->fileRepository->writeData($data, $filename, FileSystemInterface::EXISTS_REPLACE);

    $fileUrlString = $this->fileUrlGenerator->generate($filename)->toString();

    // Assign the file to JSON data file field of life event of given ID.
    $life_event = $this->getLifeEventById($id, $this->mode);
    if ($life_event) {
      $field_name = '';
      if ($this->mode == "published") {
        $field_name = 'field_json_data_file_path';
      }
      elseif ($this->mode == "draft") {
        $field_name = 'field_draft_json_data_file_path';
      }
      $life_event->set($field_name, [
        'value' => $fileUrlString,
      ]);
      $life_event->save();
    }

    return new JsonResponse([
      'data' => "Saved JSON data to " . $fileUrlString,
      'method' => 'GET',
      'status' => 200,
    ]
    );
  }

  /**
   * Gets Json Data of given life event.
   * @param $id
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The response.
   */
  public function getJsonData($id) {
    return new JsonResponse([
      'data' => $this->getData($id),
      'method' => 'GET',
      'status' => 200,
    ]
    );
  }

  /**
   * Gets data of life event form and benefits of given life event.
   * @param $id
   *  The life event ID.
   * @return mixed
   *  The JSON encoded data.
   */
  public function getData($id) {
    $life_event_form = [];
    $benefits = [];
    $result = [];

    // Get JSON data mode.
    if (empty($this->mode)) {
      $this->mode = $this->requestStack->getCurrentRequest()->query->get('mode') ?? "published";
    }

    // Get life event form node and node ID of given life event.
    $life_event_form_node = $this->getLifeEventFormById($id, $this->mode);
    if (empty($life_event_form_node)) {
      $result = [];
      $json = json_encode($result, JSON_PRETTY_PRINT);
      print_r("<p>JSON Data<pre>");
      print_r($json);
      print_r("</pre>");
      return $result;
    }

    // Get node ID of life event form.
    $life_event_form_node_id = $life_event_form_node->id();

    // Build life event form.
    $life_event_form = [
      "id" => $life_event_form_node->get('field_b_id')->value,
      "timeEstimate" => $life_event_form_node->get('field_b_time_estimate')->value ?? "",
      "titlePrefix" => $life_event_form_node->get('field_b_title_prefix')->value ?? "",
      "title" => $life_event_form_node->get('title')->value ?? "",
      "summary" => $life_event_form_node->get('field_b_summary')->value ?? "",
    ];

    // Get Relevant Benefits.
    $relevant_benefits = $life_event_form_node->get('field_b_relevant_benefits')->referencedEntities();

    // Build Relevant Benefits.
    $life_event_form_relevant_benefits = [];
    foreach ($relevant_benefits as $relevant_benefit) {

      // Get life event node by its ID.
      $id = current($relevant_benefit->get('field_b_life_event_form')->referencedEntities())->get('field_b_id')->value;
      $life_event_node = $this->getLifeEventById($id, $this->mode);

      // Get search title of life event.
      $life_event_search_title = $life_event_node->get('field_b_search_title')->value;

      $life_event_form_relevant_benefit = [
        "title" => current($relevant_benefit->get('field_b_life_event_form')->referencedEntities())->get('title')->value ?? "",
        "searchTitle" => $life_event_search_title ?? "",
        "body" => $relevant_benefit->get('field_b_body')->value ?? "",
        "link" => $relevant_benefit->get('field_b_link')->value ?? "",
        "cta" => $relevant_benefit->get('field_b_cta')->value ?? "",
        "lifeEventId" => current($relevant_benefit->get('field_b_life_event_form')->referencedEntities())->get('field_b_id')->value ?? "",
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
        "description" => $section->get('field_b_description')->value ?? "",
      ];

      // Get criterias of a section.
      $criterias = $section->get('field_b_criterias')->referencedEntities();

      // Build criteria fieldsets.
      $criteria_fieldsets = [];
      foreach ($criterias as $criteria) {
        $criteria_fieldset = [];
        if ($criteria->type->target_id == "b_levent_elg_criteria") {
          $criteria_fieldset = $this->buildCriteriaFieldset($criteria);
        }
        elseif ($criteria->type->target_id == "b_levent_elg_criteria_group") {
          $criteria_fieldset = $this->buildCriteriaGroupFieldset($criteria);
        }
        $criteria_fieldsets[]['fieldset'] = $criteria_fieldset;
      }

      $life_event_form_section['fieldsets'] = $criteria_fieldsets;
      $life_event_form_sections[]['section'] = $life_event_form_section;
    }

    $life_event_form['sectionsEligibilityCriteria'] = $life_event_form_sections;

    // Get benefits of given life event form.
    $benefit_nodes = $this->getBenefitsByLifeEventForm($life_event_form_node_id, $this->mode);

    // Build benefits.
    foreach ($benefit_nodes as $benefit_node) {
      if (!empty($benefit_node)) {
        $benefits[]["benefit"] = $this->buildBenefit($benefit_node);
      }
    }

    // Encode JSON data.
    $result = [
      "lifeEventForm" => $life_event_form,
      "benefits" => $benefits,
    ];
    $json = json_encode($result, JSON_PRETTY_PRINT);

    if ($this->displayData) {
      print_r("<p>JSON Data<pre>");
      print_r($json);
      print_r("</pre>");
    }

    return $result;
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
      "description" => $criteria->field_b_description->value ?? "",
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
  public function buildCriteriaFieldset($criteria) {
    $criteria_fieldset = [];

    // Get criteria node.
    $target_id = $criteria->get('field_b_criteria_key')->target_id;
    $criteria_node = $this->getCriteria($target_id, $this->mode);

    // Do not build missing criteria.
    if (empty($criteria_node)) {
      return $criteria_fieldset;
    }

    // Build criteria fieldset.
    $criteria_fieldset = [
      "criteriaKey" => current($criteria->get('field_b_criteria_key')->referencedEntities())->get('field_b_id')->value,
      "legend" => $criteria->get('field_b_legend')->value ?? "",
      "required" => $criteria->get('field_b_required')->value ? TRUE : FALSE,
      "hint" => $criteria->get('field_b_hint')->value ?? "",
    ];

    // Build inputCriteria.
    $inputCriteria = [
      "id" => $criteria_node->get('field_b_id')->value,
      "type" => $criteria_node->get('field_b_type')->value,
      "name" => $criteria_node->get('field_b_name')->value ?? "",
      "label" => $criteria_node->get('field_b_label')->value ?? "",
      "hasChild" => $criteria_node->get('field_b_has_child')->value ? TRUE : FALSE,
      "childDependencyOption" => $criteria_node->get('field_b_child_dependency_option')->value ?? "",
    ];

    $criteria_values = [];

    if ($criteria_node->get('field_b_type')->value == 'date' || $criteria_node->get('field_b_type')->value == "Date") {
      $criteria_values[] = [
        "default" => "",
        "value" => (object) [],
      ];
    }

    $b_values = $criteria_node->get('field_b_values')->getValue();
    foreach ($b_values as $b_value) {
      $criteria_values[] = [
        "option" => $b_value["value"],
        "value" => $b_value["value"],
      ];
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
        }
        elseif ($criteria_1->type->target_id == "b_levent_elg_criteria_group") {
          $criteria_fieldset_1 = $this->buildCriteriaGroupFieldset($criteria_1);
        }
        $criteria_fieldset["children"][]["fieldsets"][]['fieldset'] = $criteria_fieldset_1;
      }
    }

    return $criteria_fieldset;
  }

  /**
   * Builds benefit data of given benefit node.
   * @param $node
   * @return array
   */
  public function buildBenefit($node) {
    $benefit = [];

    // Build benefit.
    $benefit = [
      "title" => $node->get('title')->value,
      "summary" => $node->get('field_b_summary')->value ?? "",
      "SourceLink" => $node->get('field_b_source_link')->value ?? "",
      "SourceIsEnglish" => $node->get('field_b_source_is_english')->value ? TRUE : FALSE,
    ];

    // Get agency node and build benefit agency.
    $target_id = $node->get('field_b_agency')->target_id;
    $agency = $this->getAgency($target_id, $this->mode);
    if ($agency) {
      $benefit["agency"] = [
        "title" => $agency->get('title')->value,
        "summary" => $agency->get('field_b_summary')->value ?? "",
        "lede" => $agency->get('field_b_lede')->value ?? "",
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

    // Build eligibilities.
    $benefit_eligibilitys = [];
    $eligibilities = $node->get('field_b_eligibility')->referencedEntities();
    foreach ($eligibilities as $eligibility) {
      $benefit_eligibility = [];

      $target_id = $eligibility->get('field_b_criteria_key')->target_id;
      $criteria_node = $this->getCriteria($target_id, $this->mode);
      if ($criteria_node) {
        $ckey = $criteria_node->get('field_b_criteria_key')->value;

        $benefit_eligibility['criteriaKey'] = $ckey;
        $benefit_eligibility['label'] = $eligibility->get('field_b_label')->value ?? "";

        $acceptableValues = $eligibility->get('field_b_acceptable_values')->getValue();
        foreach ($acceptableValues as $acceptableValue) {
          $benefit_eligibility['acceptableValues'][] = $acceptableValue['value'];
        }

        $benefit_eligibilitys[] = $benefit_eligibility;
      }

    }

    $benefit['eligibility'] = $benefit_eligibilitys;

    return $benefit;
  }

}
