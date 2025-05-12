<?php

namespace Drupal\usagov_chatbot\Controller;

use Symfony\Component\HttpFoundation\Response;
use Codewithkyrian\ChromaDB\ChromaDB;

/**
 * Class GenerateRAGController.
 */
class GenerateRAGController {

  /**
   * Function test.
   */
  public function test() {
    try {

      $chromaDB = ChromaDB::client();

      // $chromaDB = ChromaDB::factory()
      //   ->withHost('http://172.19.0.2')
      //   ->withPort(8000)
      //   ->withDatabase('new_database')
      //   ->withTenant('new_tenant')
      //   ->connect();

      $version = $chromaDB->version();

      // $version = file_get_contents('http://172.19.0.2:8000/api/v1/version');

      $output = "<h2>ChromaDB Version: $version</h2>";

      return new Response($output);
    }
    catch (\Exception $e) {
      return new Response('An error occured: ' . $e->getMessage(), 500);
    }
  }

}
