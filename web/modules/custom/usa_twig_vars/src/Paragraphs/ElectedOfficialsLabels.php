<?php

namespace Drupal\usa_twig_vars\Paragraphs;

use Drupal\Core\Language\LanguageInterface;
use Drupal\paragraphs\Entity\Paragraph;

class ElectedOfficialsLabels {

  use MapOverridesTrait;

  /**
   * Merges customized labels with defaults for the current languages.
   *
   * This value is used as "ceoText" when building the UI for contact elected
   * officials results page.
   *
   * @return array<string, mixed>
   */
  public function getUspsErrors(Paragraph $para, LanguageInterface $lang): array {
    $overrides = $this->mapOverrides(
      $para,
      map: [
        'field_ceo_street_error' => 'invalid-street',
        'field_usps_no_street_error' => 'no-street',
        'field_usps_invalid_city_error' => 'invalid-city',
        'field_usps_invalid_zip_error' => 'invalid-zip',
      ]
    );

    $defaults = $this->getFormDefaults($lang);
    return array_replace_recursive($defaults, $overrides);
  }

  /**
   * Merges customized labels with defaults for the current languages.
   *
   * This value is used as "ceoText" when building the UI for contact elected
   * officials results page.
   *
   * @return array<string, mixed>
   */
  public function getResultsLabels(Paragraph $para, LanguageInterface $lang): array {
    // Get the non-empty paragraph fields into an array structure for merging.
    // 1. Map fields to the defaults keys.
    $overrides = $this->mapOverrides(
      $para,
      map: [
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
      ]
    );

    // Turn levels into integer keys.
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

    // Handle the URL for path-contact since we must look up the path alias.
    if ($para->get('field_ceo_results_contact_path')->getValue()) {
      $target_node = $para->get('field_ceo_results_contact_path')
        ->referencedEntities()[0];
      $overrides['path-contact'] = $target_node->toUrl()->toString();
    }

    $defaults = $this->getResultsDefaults($lang);
    return array_replace_recursive($defaults, $overrides);
  }

  /**
   * Merges customized labels with defaults for the current languages.
   *
   * This value is used as "ceoText" when building the UI for contact elected
   * officials results page.
   *
   * @return array<string, mixed>
   */
  public function getEmailLabels(Paragraph $para, LanguageInterface $lang): array {
    $overrides = $this->mapOverrides(
      $para,
      map: [
        'field_ceo_topic_missing_error' => 'topic',
        'field_ceo_about_missing_label' => 'about',
        'field_ceo_action_missing_label' => 'action',
        'field_ceo_new_window_msg' => 'new_window',
        'field_ceo_subject' => 'subject',
        'field_ceo_issue_prefix' => 'issue',
        'field_ceo_concern_prefix' => 'concern',
        'field_ceo_idea_prefix' => 'idea',
      ],
    );

    $defaults = $this->getEmailDefaults($lang);
    return array_replace_recursive($defaults, $overrides);
  }

  /**
   * @return array<string, mixed>
   */
  private function getFormDefaults(LanguageInterface $lang): array {
    return match ($lang->getId()) {
      'en' => [
        'invalid-street' => 'Please enter a valid street address.',
        'no-street' => 'Address not found. Please enter a valid address.',
        'invalid-city' => 'City not found. Please enter a valid city.',
        'invalid-zip' => 'Please enter a valid 5-digit ZIP code.',
      ],
      'es' => [
        'invalid-street' => 'Por favor, escriba una dirección válida.',
        'no-street' => 'Dirección no encontrada. Por favor, escriba una dirección válida.',
        'invalid-city' => 'Ciudad no encontrada. Por favor, escriba una ciudad válida.',
        'invalid-zip' => 'Por favor, escriba un código postal válido de 5 dígitos.',
      ],
      default => throw new \InvalidArgumentException("Unrecognized language argument"),
    };
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
      default => throw new \InvalidArgumentException("Unrecognized language argument"),
    };
  }

  /**
   * @return array<string, mixed>
   */
  private function getEmailDefaults(LanguageInterface $lang): array {
    return match ($lang->getId()) {
      'en' => [
        'topic' => 'Please fill out the topic field.',
        'about' => 'Please fill out the about field.',
        'action' => 'Please fill out the action field.',
        'new_window' => 'Your message has been written, and a new window on the screen has opened with your email. Make sure to click send!',
        'subject' => 'A Message From a Constituent',
        'issue' => 'The issue that I am inquiring about is:',
        'concern' => 'My concerns regarding this issue are:',
        'idea' => 'And my ideas to address this issue are:',
      ],
      'es' => [
        'topic' => 'Por favor, escriba el tema. ',
        'about' => 'Por favor, escriba qué quiere decir acerca del tema.',
        'action' => 'Por favor, escriba su petición para el funcionario electo.',
        'new_window' => 'Su mensaje ha sido escrito y se ha abierto una nueva ventana en la pantalla con su correo electrónico. Asegúrate de hacer clic en enviar ("send").',
        'subject' => 'Un mensaje de un ciudadano',
        'issue' => 'El tema sobre el que estoy preguntando es: ',
        'concern' => 'Mis inquietudes con respecto a este tema son:',
        'idea' => 'Y mis ideas para abordar este cuestión son:',
      ],
      default => throw new \InvalidArgumentException("Unrecognized language argument"),
    };
  }

}
