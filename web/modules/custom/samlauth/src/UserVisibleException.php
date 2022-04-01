<?php

namespace Drupal\samlauth;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * A RuntimeException that contains messages that are safe to expose to users.
 *
 * The message is translated into Drupal's current active language so that
 * getMessage() returns the translation.
 */
class UserVisibleException extends \RuntimeException {

  /**
   * The original (untranslated template) message passed in to the constructor.
   *
   * @var string
   */
  protected $originalMessage;

  /**
   * The replacement patterns passed into the constructor.
   *
   * @var array
   */
  protected $replacements;

  /**
   * UserVisibleException constructor.
   *
   * @param string $message
   *   Untranslated message which may contain placeholders.
   * @param array $args
   *   (optional) An associative array of replacements to make after
   *   translation. Using '@' for the first character of the keys is encouraged;
   *   '%' may come out strange when being rendered outside of HTML output. See
   *   \Drupal\Component\Render\FormattableMarkup::placeholderFormat() for
   *   details.
   * @param int $code
   *   (optional) The exception code.
   * @param \Throwable|null $previous
   *   (optional) The previous throwable used for the exception chaining.
   *
   * @see \Drupal\Component\Render\FormattableMarkup::placeholderFormat()
   */
  public function __construct($message = '', array $args = [], $code = 0, \Throwable $previous = NULL) {
    $this->originalMessage = $message;
    $this->replacements = $args;
    // The getMessage() method must return the translation and is 'final', so
    // we must set the already-translated message.
    $markup = new TranslatableMarkup($message, $args);
    parent::__construct($markup->render(), $code, $previous);
  }

  /**
   * Gets the untranslated message with any placeholders intact.
   *
   * @return string
   *   The untranslated message.
   */
  public function getOriginalMessage() {
    return $this->originalMessage;
  }

  /**
   * Gets the replacements array.
   *
   * @return array
   *   The replacements array.
   */
  public function getReplacements() {
    return $this->replacements;
  }

  /**
   * Updates the translation returned by getMessage().
   *
   * @param string $langcode
   *   The language code to translate the message into.
   */
  public function retranslate($langcode) {
    $markup = new TranslatableMarkup($this->originalMessage, $this->replacements, ['langcode' => $langcode]);
    $this->message = $markup->render();
  }

}
