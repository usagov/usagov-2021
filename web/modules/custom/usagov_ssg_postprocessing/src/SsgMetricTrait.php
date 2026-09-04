<?php

namespace Drupal\usagov_ssg_postprocessing;

/**
 * Emits grep-friendly static-site generation metrics during Tome runs.
 */
trait SsgMetricTrait {

  protected function ssgMetricStart(): float {
    return microtime(TRUE);
  }

  /**
   * @param array<string, mixed> $fields
   */
  protected function ssgMetric(string $phase, string $status, array $fields = []): void {
    $parts = [
      'SSG_METRIC',
      'source=php',
      'class=' . static::class,
      'phase=' . $this->ssgMetricValue($phase),
      'status=' . $this->ssgMetricValue($status),
      'ts=' . time(),
    ];

    foreach ($fields as $key => $value) {
      $parts[] = $this->ssgMetricValue((string) $key) . '=' . $this->ssgMetricValue($value);
    }

    error_log(implode(' ', $parts));
  }

  /**
   * @param array<string, mixed> $fields
   */
  protected function ssgMetricEnd(string $phase, float $start, string $status = 'end', array $fields = []): void {
    $fields = ['duration_ms' => (int) round((microtime(TRUE) - $start) * 1000)] + $fields;
    $this->ssgMetric($phase, $status, $fields);
  }

  protected function ssgMetricValue(mixed $value): string {
    if (is_bool($value)) {
      $value = $value ? '1' : '0';
    }
    elseif ($value === NULL) {
      $value = 'null';
    }

    $normalized_value = preg_replace('/\s+/', '_', (string) $value);

    return $normalized_value === '' ? 'empty' : $normalized_value;
  }

}
