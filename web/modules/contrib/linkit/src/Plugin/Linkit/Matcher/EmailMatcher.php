<?php

namespace Drupal\linkit\Plugin\Linkit\Matcher;

use Drupal\Component\Utility\Html;
use Drupal\linkit\MatcherBase;
use Drupal\linkit\Suggestion\DescriptionSuggestion;
use Drupal\linkit\Suggestion\SuggestionCollection;

/**
 * Provides specific linkit matchers for emails.
 *
 * @Matcher(
 *   id = "email",
 *   label = @Translation("Email"),
 * )
 */
class EmailMatcher extends MatcherBase {

  /**
   * {@inheritdoc}
   */
  public function execute($string) {
    $suggestions = new SuggestionCollection();

    // Check for an e-mail address then return an e-mail match and create a
    // mail-to link if appropriate.
    if (filter_var($string, FILTER_VALIDATE_EMAIL)) {
      $suggestion = new DescriptionSuggestion();
      $suggestion->setLabel($this->t('E-mail @email', ['@email' => $string]))
        ->setPath('mailto:' . Html::escape($string))
        ->setGroup($this->t('E-mail'))
        ->setDescription($this->t('Opens your mail client ready to e-mail @email', ['@email' => $string]));

      $suggestions->addSuggestion($suggestion);
    }
    return $suggestions;
  }

}
