<?php

namespace Drupal\samlauth\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\views\Plugin\views\field\LinkBase;
use Drupal\views\ResultRow;

/**
 * Field handler to present a link to delete an authmap entry.
 *
 * Stripped down version of EntityLinkBase. Crude; Views integration will be
 * improved upon once (hopefully) moved into the externalauth module.
 * Actually I'm not thrilled about using LinkBase because it still contains a
 * lot of entity magic that we don't need, and we likely don't need a lot of
 * its alter options either.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("samlauth_link_delete")
 */
class AuthmapDeleteLink extends LinkBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // This is overridden to not call $this->getEntityTranslationRenderer()
    // which will break because we don't have an entity type. (And we assume
    // we can skip calling it because we never need to add extra tables/fields
    // in order to translate this link. As an aside: this class would be much
    // smaller if LinkBase didn't contain entity related code and if all non
    // entity related code was actually in LinkBase so we didn't need to copy
    // it from EntityLinkBase.)
    $this->addAdditionalFields();
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $row) {
    // This whole link is a quick hack because this functionality should move
    // into the externalauth module. We'll do the access checks in the form.
    return ['#markup' => $this->renderLink($row)];
  }

  /**
   * {@inheritdoc}
   */
  protected function renderLink(ResultRow $row) {
    // From EntityLink:
    if ($this->options['output_url_as_text']) {
      return $this->getUrlInfo($row)->toString();
    }
    // From LinkBase, minus addLangCode() which needs an entity. (If this needs
    // 'alter']['language' set sometimes, then we still need to work out when
    // and how.)
    $this->options['alter']['make_link'] = TRUE;
    $this->options['alter']['url'] = $this->getUrlInfo($row);
    $text = !empty($this->options['text']) ? $this->sanitizeValue($this->options['text']) : $this->getDefaultLabel();
    return $text;
  }

  /**
   * {@inheritdoc}
   */
  protected function getUrlInfo(ResultRow $row) {
    // We can get away with just passing the UID as an argument, because the
    // combination of uid+provider is unique. In the future we might have
    // storage where multiple authnames from the same provider could be linked
    // to 1 account; if that happens, we won't want to add the authname into
    // the URL directly but Crypt::hmacBase64() it so that it isn't present in
    // referer headers. (The confirm form will need to recalculate the hash(es)
    // in order to check which authname we're talking about - but assuming the
    // number of authnames per UID is low, that won't be too expensive.)
    return Url::fromRoute('samlauth.authmap_delete_form', ['uid' => $row->authmap_uid]);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultLabel() {
    return $this->t('delete');
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    // Copy from EntityLinkBase. Likely unnecessary, but harmless.
    $options = parent::defineOptions();
    $options['output_url_as_text'] = ['default' => FALSE];
    $options['absolute'] = ['default' => FALSE];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    // Copy from EntityLinkBase. Likely unnecessary, but harmless.
    $form['output_url_as_text'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Output the URL as text'),
      '#default_value' => $this->options['output_url_as_text'],
    ];
    $form['absolute'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use absolute link (begins with "http://")'),
      '#default_value' => $this->options['absolute'],
      '#description' => $this->t('Enable this option to output an absolute link. Required if you want to use the path as a link destination.'),
    ];
    parent::buildOptionsForm($form, $form_state);
    // Only show the 'text' field if we don't want to output the raw URL.
    $form['text']['#states']['visible'][':input[name="options[output_url_as_text]"]'] = ['checked' => FALSE];
  }

}
