<?php

namespace Drupal\siteimprove\Plugin\SiteimproveDomain;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\siteimprove\Plugin\SiteimproveDomainBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides simple plugin instance of Siteimprove Domain settings.
 *
 * @package Drupal\siteimprove\Plugin\SiteimproveDomain
 *
 * @SiteimproveDomain(
 *   id = "siteimprovedomain_single",
 *   label = @Translation("Single frontend domain"),
 *   description = @Translation("Set a single domain for Siteimprove. Useful if you have a different backend domain than frontend domain."),
 * )
 */
class Single extends SiteimproveDomainBase {

  /**
   * Current request service.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * {@inheritDoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, ConfigFactoryInterface $configFactory, Request $request) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $configFactory);
    $this->request = $request;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('request_stack')->getCurrentRequest()
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array &$form, FormStateInterface &$form_state, $plugin_definition) {
    parent::buildForm($form, $form_state, $plugin_definition);

    $form[$plugin_definition['id']]['single_domain'] = [
      '#type' => 'textfield',
      '#description' => $this->t('Input your domain name. If you leave out http:// or https://, the scheme will inherit the scheme of the web request.'),
      '#default_value' => $this->config('siteimprove.domain.single.settings')->get('domain'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $value = $form_state->getValue('single_domain');
    if (!preg_match('/^(https?:\/\/)?([a-zA-Z0-9][a-zA-Z0-9-_]*\.)*[a-zA-Z0-9]*[a-zA-Z0-9-_]*[[a-zA-Z0-9]+(:\d+)?$/', $value)) {
      $form_state->setErrorByName('single_domain', $this->t('Only use valid domain names in this field - no trailing slash, no trailing whitespace.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('siteimprove.domain.single.settings');
    $config->set('domain', $form_state->getValue('single_domain'))->save();
  }

  /**
   * {@inheritdoc}
   */
  public function getUrls(EntityInterface $entity) {
    $config = $this->config('siteimprove.domain.single.settings');
    $domain = $config->get('domain');
    $scheme = preg_match('/^https?:\/\//', $domain) ? '' : $this->request->getScheme() . '://';
    return [$scheme . $domain];
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['siteimprove.domain.single.settings'];
  }

}
