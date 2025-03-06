<?php

namespace Drupal\usa_twig_vars\Paragraphs;

use Drupal\Core\Language\LanguageInterface;
use Drupal\paragraphs\Entity\Paragraph;

class ChatLabels {

  use MapOverridesTrait;

  /**
   * Merges customized labels with defaults for the current languages.
   *
   * @return array<string, mixed>
   */
  public function getLabels(Paragraph $para, LanguageInterface $lang): array {
    $overrides = $this->mapOverrides(
      $para,
      map: [
        'field_chat_button' => 'chat-button',
      ]
    );

    $defaults = $this->getDefaults($lang);
    return array_replace_recursive($defaults, $overrides);
  }

  /**
   * @return array<string, mixed>
   */
  private function getDefaults(LanguageInterface $lang): array {
    return match ($lang->getId()) {
      'en' => [
        'chat-button' => 'Start a chat',
      ],
      'es' => [
        'chat-button' => 'Ir al chat',
      ],
      default => throw new \InvalidArgumentException("Unrecognized language argument"),
    };
  }

}
