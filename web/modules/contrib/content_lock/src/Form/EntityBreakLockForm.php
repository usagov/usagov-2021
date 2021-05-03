<?php

namespace Drupal\content_lock\Form;

use Drupal\content_lock\ContentLock\ContentLock;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a base class for break content lock forms.
 */
class EntityBreakLockForm extends FormBase {

  /**
   * Content lock service.
   *
   * @var \Drupal\content_lock\ContentLock\ContentLock
   */
  protected $lockService;

  /**
   * Current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * Language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * EntityBreakLockForm constructor.
   *
   * @param \Drupal\content_lock\ContentLock\ContentLock $contentLock
   *   Content lock service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Request stack service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   Language manager service.
   */
  public function __construct(ContentLock $contentLock, RequestStack $requestStack, LanguageManagerInterface $language_manager) {
    $this->lockService = $contentLock;
    $this->request = $requestStack->getCurrentRequest();
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('content_lock'),
      $container->get('request_stack'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity_type = $form_state->getValue('entity_type_id');
    $entity_id = $form_state->getValue('entity_id');
    $langcode = $form_state->getValue('langcode');
    $form_op = $form_state->getValue('form_op') ?: NULL;

    $this->lockService->release($entity_id, $langcode, $form_op, NULL, $entity_type);
    if ($form_state->get('translation_lock')) {
      $this->messenger()->addStatus($this->t('Lock broken. Anyone can now edit this content translation.'));
    }
    else {
      $this->messenger()->addStatus($this->t('Lock broken. Anyone can now edit this content.'));
    }

    // Redirect URL to the request destination or the canonical entity view.
    if ($destination = $this->request->query->get('destination')) {
      $url = Url::fromUserInput($destination);
      $form_state->setRedirectUrl($url);
    }
    else {
      $language = $this->languageManager->getLanguage($form_state->get('langcode_entity'));
      $url = Url::fromRoute("entity.$entity_type.canonical", [$entity_type => $entity_id], ['language' => $language]);
      $form_state->setRedirectUrl($url);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'break_lock_entity';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ContentEntityInterface $entity = NULL, $langcode = NULL, $form_op = NULL) {
    // Save langcode of lock, before checking if translation lock is enabled.
    // This is needed to generate the correct entity URL for the given language.
    $form_state->set('langcode_entity', $langcode);

    $translation_lock = $this->lockService->isTranslationLockEnabled($entity->getEntityTypeId());
    if (!$translation_lock) {
      $langcode = LanguageInterface::LANGCODE_NOT_SPECIFIED;
    }
    $form_state->set('translation_lock', $translation_lock);

    $form_op_lock = $this->lockService->isFormOperationLockEnabled($entity->getEntityTypeId());
    if (!$form_op_lock) {
      $form_op = '*';
    }

    $form['#title'] = $this->t('Break Lock for content @label', ['@label' => $entity->label()]);
    $form['entity_id'] = [
      '#type' => 'value',
      '#value' => $entity->id(),
    ];
    $form['entity_type_id'] = [
      '#type' => 'value',
      '#value' => $entity->getEntityTypeId(),
    ];
    $form['langcode'] = [
      '#type' => 'value',
      '#value' => $langcode,
    ];
    $form['form_op'] = [
      '#type' => 'value',
      '#value' => $form_op,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Confirm break lock'),
    ];
    return $form;
  }

  /**
   * Custom access checker for the form route requirements.
   */
  public function access(ContentEntityInterface $entity, $langcode, $form_op, AccountInterface $account) {
    return AccessResult::allowedIf($account->hasPermission('break content lock') || $this->lockService->isLockedBy($entity->id(), $langcode, $form_op, $account->id(), $entity->getEntityTypeId()));
  }

}
