<?php

namespace Drupal\usa_twig_vars\Paragraphs;

use Drupal\Core\Language\LanguageInterface;
use Drupal\paragraphs\Entity\Paragraph;

class SiteIssueReportLabels {

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
   * Maps the user submitted values to an array for use by the front-end
   *
   * Empty fields are not mapped.
   *
   * @param array<string, mixed> $map
   *
   * @return array<string, mixed>
   */
  private function mapOverrides(Paragraph $para, array $map): array {
    $overrides = [];
    foreach ($map as $src => $target) {
      $value = $para->get($src)->getValue();
      if (empty($value)) {
        continue;
      }
      $value = trim($value[0]['value']);
      if (empty($value)) {
        continue;
      }

      if (is_array($target)) {
        // Map user input to a nested array structure.
        $value = $this->asArray($target, $value);
        // Need to use array_merge_recursive here to ensure we add sub-keys
        // to existing values.
        $overrides = array_merge_recursive($overrides, $value);
      }
      else {
        $overrides[$target] = $value;
      }
    }

    return $overrides;
  }

  /**
   * Turn an array of keys ands a value into a nested array.
   *
   * input  $keys = ['foo', 'bar', 'baz'] and $value = 'Done'
   * output = ['foo' => ['bar' => ['baz => 'Done']]];
   *
   * @param string[] $keys
   * @param mixed $value
   *
   * @return array<string, mixed>
   */
  private function asArray(array $keys, $value): array {
    $key = array_shift($keys);
    if (count($keys) == 0) {
      return [$key => $value];
    }

    return [$key => $this->asArray($keys, $value)];
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
