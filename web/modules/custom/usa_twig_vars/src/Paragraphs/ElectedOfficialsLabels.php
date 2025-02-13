<?php

namespace Drupal\usa_twig_vars\Paragraphs;

use Drupal\Core\Language\LanguageInterface;
use Drupal\paragraphs\Entity\Paragraph;

class ElectedOfficialsLabels {

  /**
   * Merges customized labels with defaults for the current languages.
   *
   * This value is used as "ceoText" when building the UI for contact elected
   * officials results page.
   *
   * @return array<string, mixed>
   */
  public function getResultsLabels(Paragraph $para, LanguageInterface $lang): array {
    $overrides = [];
    $defaults = $this->getResultsDefaults($lang);

    // Get the non-empty paragraph fields into an array structure for merging.
    // 1. Map fields to the defaults keys.
    $map = [
      'field_ceo_results_error_fetch' => 'error-fetch',
      'field_ceo_results_error_address' => 'error-address',

      // The numeric keys here can't be ints, or they merge later
      // will not work. Prepending with an underscore.
      'field_ceo_fed_officials_label' => ['levels', '_0', 'heading'],
      'field_ceo_fed_officials_descr' => ['levels', '_0', 'description'],
      'field_ceo_state_officials_label' => ['levels', '_1', 'heading'],
      'field_ceo_state_descr' => ['levels', '_1', 'description'],
      'field_ceo_local_officials_label' => ['levels', '_2', 'heading'],
      'field_ceo_officials_descr' => ['levels', '_2', 'description'],

      'field_ceo_results_party' => 'party-affiliation',
      'field_ceo_results_address_label' => 'address',
      'field_ceo_results_phone_number' => 'phone-number',
      'field_ceo_results_website' => 'website',
      'field_ceo_results_via_email' => 'contact-via-email',
    ];

    foreach($map as $src => $target) {
      $value = $para->get($src)->getValue();
      if (empty($value)) {
        continue;
      }
      $value = trim($value[0]['value']);
      if (empty($value)) {
        continue;
      }

      if (is_array($target)) {
        $value = $this->asArray($target, $value);
        // Need to use array_merge_recursive here to ensure we add sub-keys
        // to existing values.
        $overrides = array_merge_recursive($overrides, $value);
      } else {
        $overrides[$target] = $value;
      }
    }
    // Turn levels into integer keys
    if (isset($overrides['levels'])) {
      // Because the levels mean something, we need to keep the
      // integer keys and ensure PHP doesn't re-index them for us.
      foreach ($overrides['levels'] as $index => $values) {
        $num = str_replace('_', '', $index);
        $overrides['levels'][$num] = $values;
        $overrides['levels'][$index] = [];
      }
      // Remove any we emptied above.
      $overrides['levels'] = array_filter($overrides['levels']);
    }

    // Handle the URL for path-contact since it's not a direct value we can retrieve.
    if ($para->get('field_ceo_results_contact_path')->getValue()) {
      $target_node = $para->get('field_ceo_results_contact_path')->referencedEntities()[0];
      $overrides['path-contact'] = $target_node->toUrl()->toString();
    }

    return array_replace_recursive($defaults, $overrides);
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
  private function getResultsDefaults(LanguageInterface $lang): array {
    return match ($lang->getId()) {
      'en' =>
      [
        'error-fetch' => 'We\'re sorry. The Google Civic Information API that provides data for this tool is not working right now. Please try again later.',
        'error-fetch-heading' => 'Data temporarily unavailable',
        'error-address' => 'There was a problem getting results for this address. Please check to be sure you entered a valid U.S. address.',
        'error-address-heading' => 'Invalid address',
        'levels' =>
          [
            0 =>
              [
                'heading' => 'Federal officials',
                'description' => 'represent you and your state in Washington, DC.',
              ],
            1 =>
              [
                'heading' => 'State officials',
                'description' => 'represent you in your state capital.',
              ],
            2 =>
              [
                'heading' => 'Local officials',
                'description' => 'represent you in your county or city.',
              ],
          ],
        'local_levels' =>
          [
            0 => 'City officials',
            1 => 'County officials',
          ],
        'party-affiliation' => 'Party affiliation',
        'address' => 'Address',
        'phone-number' => 'Phone number',
        'website' => 'Website',
        'contact-via-email' => 'Contact via email',
        'path-contact' => '/elected-officials-email',
      ],
      'es' =>
      [
        'error-fetch' => 'Lo sentimos. Pero la API de información cívica de Google que provee los datos al sistema de búsqueda no está funcionando. Por favor, intente de nuevo más tarde.',
        'error-fetch-heading' => 'Datos no disponibles temporalmente',
        'error-address' => 'Tuvimos problemas para obtener resultados con esta dirección. Por favor, verifique si ingresó una dirección válida en EE. UU.',
        'error-address-heading' => 'Dirección incorrecta',
        'levels' =>
          [
            0 =>
              [
                'heading' => 'Funcionarios federales',
                'description' => 'que le representan a usted y a su estado en Washington, DC.',
              ],
            1 =>
              [
                'heading' => 'Funcionarios estatales',
                'description' => 'que le representan en la capital de su estado.',
              ],
            2 =>
              [
                'heading' => 'Funcionarios locales',
                'description' => 'que le representan en su condado o ciudad.',
              ],
          ],
        'local_levels' =>
          [
            0 => 'Funcionarios de ciudades',
            1 => 'Funcionarios de condados',
          ],
        'party-affiliation' => 'Afiliación de partido',
        'address' => 'Dirección',
        'phone-number' => 'Teléfono',
        'website' => 'Sitio web',
        'contact-via-email' => 'Contactar por correo electrónico',
        'path-contact' => '/es/funcionarios-electos-correo-electronico',
      ],
    };
  }

}
