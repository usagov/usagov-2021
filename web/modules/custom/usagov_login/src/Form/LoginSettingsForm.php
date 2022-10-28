<?php

namespace Drupal\usagov_login\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure USAgov Login Customizations settings for this site.
 */
class LoginSettingsForm extends ConfigFormBase {

  /**
   * State storage.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  private $state;

  /**
   * LoginSettingsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Configuration factory.
   * @param \Drupal\Core\State\StateInterface $state
   *   State storage.
   */
  public function __construct(ConfigFactoryInterface $config_factory, StateInterface $state) {
    parent::__construct($config_factory);
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('state'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'usagov_login_login_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['usagov_login.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['sso_login_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('SSO Login Path'),
      '#default_value' => $this->config('usagov_login.settings')
        ->get('sso_login_path'),
      '#required' => FALSE,
    ];

    $form['sso_login_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Login Text'),
      '#default_value' => $this->config('usagov_login.settings')
        ->get('sso_login_text'),
      '#required' => FALSE,
    ];

    $form['display_local'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display the local login form'),
      '#default_value' => $this->state->get('usagov_login_local_form', 0),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $this->state->set('usagov_login_local_form', $form_state->getValue('display_local'));

    $this->config('usagov_login.settings')
      ->set('sso_login_path', $form_state->getValue('sso_login_path'))
      ->save();

    $this->config('usagov_login.settings')
      ->set('sso_login_text', $form_state->getValue('sso_login_text'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
