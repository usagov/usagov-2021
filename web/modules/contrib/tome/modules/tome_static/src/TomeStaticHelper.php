<?php

namespace Drupal\tome_static;

use Symfony\Component\HttpFoundation\Request;

/**
 * Provides helpers for the Tome Static module.
 *
 * @internal
 */
trait TomeStaticHelper {

  /**
   * Sets the base URL for a given request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param string $base_url
   *   The base URL.
   *
   * @return array
   *   An array meant to be passed to ::restoreBaseUrl
   */
  public static function setBaseUrl(Request $request, $base_url) {
    $original_params = [
      'server' => $request->server->all(),
      'headers' => $request->headers->all(),
    ];
    $server = $request->server->all();
    // Original credit to the Drush team for this logic.
    $base_url = parse_url($base_url);
    $base_url += [
      'scheme' => NULL,
      'path' => '',
      'host' => NULL,
      'port' => NULL,
    ];
    $server['HTTP_HOST'] = $base_url['host'];
    if ($base_url['scheme'] === 'https') {
      $server['HTTPS'] = 'on';
    }
    if ($base_url['port']) {
      $server['HTTP_HOST'] .= ':' . $base_url['port'];
    }
    $server['SERVER_PORT'] = $base_url['port'];
    $server['REMOTE_ADDR'] = '127.0.0.1';
    $request->server->replace($server);
    $request->headers->set('HOST', $base_url['host']);
    return $original_params;
  }

  /**
   * Restores the base URL for a request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param array $original_params
   *   The return value of ::setBaseUrl.
   */
  public static function restoreBaseUrl(Request $request, array $original_params) {
    $request->server->replace($original_params['server']);
    $request->headers->replace($original_params['headers']);
  }

}
