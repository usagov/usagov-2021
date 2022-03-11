<?php

namespace Drupal\tome_static\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\tome_static\EventSubscriber\StaticPreviewRequestSubscriber;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Contains a form for initializing a static preview session.
 *
 * @internal
 */
class StaticPreviewForm extends FormBase {

  /**
   * The session.
   *
   * @var \Symfony\Component\HttpFoundation\Session\Session
   */
  protected $session;

  /**
   * StaticPreviewForm constructor.
   *
   * @param \Symfony\Component\HttpFoundation\Session\Session $session
   *   The session.
   */
  public function __construct(Session $session) {
    $this->session = $session;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('session')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'tome_static_generator_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['description'] = [
      '#markup' => '<p>' . $this->t('Submitting this form will initiate a preview session of your static site. You can exit the preview by clicking the link at the top of preview pages.') . '</p>',
    ];

    $form['warning'] = [
      '#markup' => '<p>' . $this->t('Note that static assets (CSS, JS, images, etc.) will not necessarily be served from the static directory, so please make a final review on a staging static domain if possible.') . '</p>',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Preview'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->session->set(StaticPreviewRequestSubscriber::SESSION_KEY, TRUE);
    $form_state->setRedirectUrl(Url::fromUserInput('/'));
  }

}
