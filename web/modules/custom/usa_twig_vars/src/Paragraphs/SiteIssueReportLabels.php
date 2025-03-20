<?php

namespace Drupal\usa_twig_vars\Paragraphs;

use Drupal\Core\Language\LanguageInterface;
use Drupal\paragraphs\Entity\Paragraph;

class SiteIssueReportLabels {

  use MapOverridesTrait;

  /**
   * Merges customized labels with defaults for the current languages.
   *
   * This value is used as "ceoText" when building the UI for contact elected
   * officials results page.
   *
   * @return array<string, mixed>
   */
  public function getFormLabels(Paragraph $para, LanguageInterface $lang): array {
    $overrides = $this->mapOverrides(
      $para,
      map: [
        'field_sirf_error_heading' => 'error-heading',
        'field_sirf_description_error' => 'missing-description',
        'field_sirf_email_error' => 'missing-email',
        'field_sirf_name_error' => 'missing-name',
        'field_sirf_submit_button' => 'submit-button',
        'field_sirf_all_required_label' => 'label-all-required',
        'field_sirf_your_name_label' => 'label-your-name',
        'field_sirf_your_email_label' => 'label-your-email',
        'field_sirf_description_label' => 'label-your-description',
        'field_sirf_char_limit_msg' => 'label-char-limit',
      ]
    );

    $defaults = $this->getFormDefaults($lang);
    return array_replace_recursive($defaults, $overrides);
  }

  /**
   * @return array<string, mixed>
   */
  private function getFormDefaults(LanguageInterface $lang): array {
    return match ($lang->getId()) {
      'en' => [
        'error-heading' => 'Your information contains errors',
        'missing-description' => 'Fill out the description field',
        'missing-email' => 'Fill out the email field',
        'missing-name' => 'Fill out the name field',
        'submit-button' => 'Submit',
        'label-all-required' => 'All fields are required.',
        'label-your-name' => 'First name',
        'label-your-email' => 'Email address',
        'label-your-description' => 'Describe the issue here. Please include the web address (URL) of the page with the problem.',
        'label-char-limit' => 'Maximum length is 1000 characters.',
      ],
      'es' => [
        'error-heading' => 'Su información contiene errores',
        'missing-description' => 'Escriba la descripción',
        'missing-email' => 'Escriba su email',
        'missing-name' => 'Escriba su nombre',
        'submit-button' => 'Enviar',
        'label-all-required' => 'Todos los campos son obligatorios.',
        'label-your-name' => 'Su nombre',
        'label-your-email' => 'Su email',
        'label-your-description' => 'Escriba el problema que desea reportar aquí. Por favor Incluya la dirección web (URL) de la página con el problema.',
        'label-char-limit' => 'Por favor limite su comentario a 1000 caracteres.',
      ],
      default => throw new \InvalidArgumentException("Unrecognized language argument"),
    };
  }

}
