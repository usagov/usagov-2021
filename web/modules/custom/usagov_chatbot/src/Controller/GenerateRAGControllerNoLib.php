<?php

namespace Drupal\usagov_chatbot\Controller;

use Symfony\Component\HttpFoundation\Response;
use GuzzleHttp\Client;

/**
 * Class GenerateRAGController_nolib.
 */
class GenerateRAGControllerNoLib {

  /**
   * Function test.
   */
  public function test() {
    try {

      $client = new Client(['base_uri' => 'http://172.19.0.2:8000']);

      $response = $client->post('/api/v1/collections', [
        'json' => ['name' => 'buildragwithphp'],
      ]);

      $data = json_decode($response->getBody(), TRUE);
      $collectionId = $data['id'];

      return new Response(dump($collectionId));
    }
    catch (\Exception $e) {
      return new Response('An error occured: ' . $e->getMessage(), 500);
    }
  }

}
