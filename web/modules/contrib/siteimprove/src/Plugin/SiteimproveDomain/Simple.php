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
 * @SiteimproveDomain(
 *   id = "siteimprovedomain_simple",
 *   label = @Translation("Default domain"),
 *   description = @Translation("Don't set anything and use Drupal's default settings"),
 * )
 */
class Simple extends SiteimproveDomainBase {

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

    $form[$plugin_definition['id']]['description'] = [
      '#type' => 'markup',
      '#markup' => $this->t("This plugin doesn't contain any settings."),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // @TODO: Can we remove this?
    parent::submitForm($form, $form_state);
  }

  /**
   * Return default domain.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   We don't use the entity in this plugin.
   *
   * @return array
   *   Array of domains.
   */
  public function getUrls(EntityInterface $entity) {
    $domain = $this->request->getScheme() . '://' . $this->request->getHttpHost();
    return [$domain];
  }

}
