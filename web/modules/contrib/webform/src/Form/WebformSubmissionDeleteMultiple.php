<?php

namespace Drupal\webform\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\webform\EntityStorage\WebformEntityStorageTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Provides a webform submission deletion confirmation form.
 */
class WebformSubmissionDeleteMultiple extends ConfirmFormBase {

  use WebformEntityStorageTrait;

  /**
   * The array of webform_submissions to delete.
   *
   * @var string[][]
   */
  protected $webformSubmissionInfo = [];

  /**
   * The tempstore factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->tempStoreFactory = $container->get('tempstore.private');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'webform_submission_multiple_delete_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->formatPlural(count($this->webformSubmissionInfo), 'Are you sure you want to delete this submission?', 'Are you sure you want to delete these submissions?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.webform_submission.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\webform\WebformSubmissionInterface[] $webform_submissions */
    $webform_submissions = $this->tempStoreFactory->get('webform_submission_multiple_delete_confirm')->get(\Drupal::currentUser()->id());
    if (empty($webform_submissions)) {
      return new RedirectResponse($this->getCancelUrl()->setAbsolute()->toString());
    }

    $form['webform_submissions'] = [
      '#theme' => 'item_list',
      '#items' => array_map(function ($webform_submission) {
        return $webform_submission->label();
      }, $webform_submissions),
    ];
    $form = parent::buildForm($form, $form_state);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\webform\WebformSubmissionInterface[] $webform_submissions */
    $webform_submissions = $this->tempStoreFactory->get('webform_submission_multiple_delete_confirm')->get(\Drupal::currentUser()->id());
    if ($form_state->getValue('confirm') && !empty($webform_submissions)) {
      $this->getSubmissionStorage()->delete($webform_submissions);
      $this->logger('content')->notice('Deleted @count submission.', ['@count' => count($webform_submissions)]);
      $this->tempStoreFactory->get('webform_submission_multiple_delete_confirm')->delete(\Drupal::currentUser()->id());
    }

    $form_state->setRedirect('entity.webform_submission.collection');
  }

}
