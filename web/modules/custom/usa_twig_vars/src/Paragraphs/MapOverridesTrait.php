<?php

namespace Drupal\usa_twig_vars\Paragraphs;

use Drupal\paragraphs\Entity\Paragraph;

trait MapOverridesTrait {

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
    if (count($keys) === 0) {
      return [$key => $value];
    }

    return [$key => $this->asArray($keys, $value)];
  }

}
