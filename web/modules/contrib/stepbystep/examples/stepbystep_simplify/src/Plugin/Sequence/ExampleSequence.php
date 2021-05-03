<?php

namespace Drupal\stepbystep_simplify\Plugin\Sequence;

use Drupal\stepbystep\Plugin\SequenceBase;

/**
 * Example Step by Step sequence.
 *
 * @Sequence(
 *   id = "stepbystep_simplify_site_settings",
 *   route = "stepbystep_simplify.example",
 *   name = @Translation("Simplify editing site settings"),
 *   description = @Translation("You can work through them in a sensible order."),
 * )
 */
class ExampleSequence extends SequenceBase {

  /**
   * {@inheritdoc}
   */
  public function getSteps() {
    return [
      'site_details' => [
        'route' => 'system.site_information_settings',
        'title' => $this->t('Site details'),
        'form_id' => 'system_site_information_settings',
        'form_elements' => ['site_information'],
      ],
      'pages' => [
        'route' => 'system.site_information_settings',
        'title' => $this->t('Front page'),
        'form_id' => 'system_site_information_settings',
        'form_elements' => ['front_page', 'error_page'],
        // Demonstrates overrides. Changes 'front page' to 'home page'.
        'overrides' => [
          'system_site_information_settings' => [
            'front_page][site_frontpage' => [
              'title' => $this->t('Default home page'),
              'description' => $this->t('Optionally, specify a relative URL to display as the home page. Leave blank to display the default home page.'),
            ],
          ],
        ],
      ],
    ];
  }

}
