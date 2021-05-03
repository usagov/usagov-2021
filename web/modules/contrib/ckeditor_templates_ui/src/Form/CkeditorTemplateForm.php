<?php

namespace Drupal\ckeditor_templates_ui\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implement config form for Ckeditor template.
 */
class CkeditorTemplateForm extends EntityForm {

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, FileSystemInterface $file_system) {
    $this->entityTypeManager = $entityTypeManager;
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('file_system')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $template = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $template->label(),
      '#description' => $this->t('Your Template title'),
      '#required' => TRUE,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $template->id(),
      '#machine_name' => [
        'exists' => [$this, 'exist'],
      ],
      '#disabled' => !$template->isNew(),
      '#required' => TRUE,
    ];
    $form['description'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Description'),
      '#default_value' => $template->getDescription(),
      '#description' => $this->t('Your Template description'),
    ];
    $image = $template->get('image');
    if ($image) {
      $image_markup = '<div class="form-item image-preview" style="max-width: 200px; max-height: 200px;">';
      $image_markup .= '<img src="' . file_create_url($image) . '" alt="' . $this->t('Preview') . '" />';
      $image_markup .= '</div>';
      $form['image_preview'] = [
        '#type' => 'inline_template',
        '#template' => $image_markup,
      ];
    }
    $form['image'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Image path for this template'),
      '#default_value' => $image,
      '#description' => $this->t('Examples: public://test.png, modules/my_module/test.png, themes/my_theme/test.png, //example.com/test.jpg'),
    ];
    $form['image_upload'] = [
      '#title' => $this->t('Upload image for this template'),
      '#type' => 'file',
      '#description' => $this->t('You can use this field if you need to upload the file to the server. Allowed extensions: gif png jpg jpeg.'),
      '#upload_validators' => [
        'file_validate_is_image' => [],
        'file_validate_extensions' => ['gif png jpg jpeg'],
        'file_validate_size' => [25600000],
      ],
    ];
    $form['html'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Body'),
      '#description' => $this->t('The predefined ckeditor template body'),
      '#required' => TRUE,
    ];
    if (!$template->isNew()) {
      $form['html']['#format'] = $template->getHtml()['format'];
      $form['html']['#default_value'] = $template->getHtml()['value'];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Check for a new uploaded image.
    if (!$form_state->getErrors()) {
      $file = _file_save_upload_from_form($form['image_upload'], $form_state, 0);
      if ($file) {
        // Put the temporary file in form_values so we can save it on submit.
        $form_state->setValue('image_upload', $file);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $template = $this->entity;

    $file = $form_state->getValue('image_upload');
    if ($file) {
      $file_dir = 'public://ckeditor-templates';
      if (!is_dir($file_dir)) {
        $this->fileSystem->prepareDirectory($file_dir, FileSystemInterface::CREATE_DIRECTORY);
      }
      $file_destination = 'public://ckeditor-templates/' . $file->getFilename();
      $filename = $this->fileSystem->copy($file->getFileUri(), $file_destination);
      if ($filename) {
        $template->set('image', $filename);
      }
    }

    $status = $template->save();

    if ($status) {
      $this->messenger()->addMessage($this->t('Saved the %label Ckeditor Template.', [
        '%label' => $template->label(),
      ]));
    }
    else {
      $this->messenger()->addMessage($this->t('The %label Ckeditor Template was not saved.', [
        '%label' => $template->label(),
      ]));
    }

    $form_state->setRedirect('entity.ckeditor_template.collection');
  }

  /**
   * Helper function to check if ckeditor_template configuration entity exists.
   */
  public function exist($id) {
    $entity = $this->entityTypeManager->getStorage('ckeditor_template')->getQuery()
      ->condition('id', $id)
      ->execute();
    return (bool) $entity;
  }

}
