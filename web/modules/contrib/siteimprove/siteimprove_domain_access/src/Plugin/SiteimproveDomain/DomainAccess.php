<?php

namespace Drupal\siteimprove_domain_access\Plugin\SiteimproveDomain;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\siteimprove\Plugin\SiteimproveDomainBase;
use Drupal\siteimprove\Plugin\SiteimproveDomainInterface;

/**
 * Provides simple plugin instance of Siteimprove Domain settings.
 *
 * @SiteimproveDomain(
 *   id = "siteimprovedomain_domain_access",
 *   label = @Translation("Domain Access support"),
 *   description = @Translation("Automatically use the domains configured in Domain Access as Siteimprove frontend domains."),
 * )
 */
class DomainAccess extends SiteimproveDomainBase implements SiteimproveDomainInterface {

  public function buildForm(array &$form, FormStateInterface &$form_state, $plugin_definition) {
    parent::buildForm($form, $form_state, $plugin_definition);

    $form[$plugin_definition['id']]['description'] = [
      '#type' => 'markup',
      '#markup' => $this->t("This plugin doesn't contain any settings. Domain names are automatically fetched from Domain Access."),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getUrls(EntityInterface $entity) {
    $domain = \Drupal::service('domain_access.manager');
    return $domain->getContentUrls($entity);
  }

}
