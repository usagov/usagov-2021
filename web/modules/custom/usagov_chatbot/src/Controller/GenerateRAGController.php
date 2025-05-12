<?php

namespace Drupal\usagov_chatbot\Controller;

use Symfony\Component\HttpFoundation\Response;
use Codewithkyrian\ChromaDB\ChromaDB;
use Codewithkyrian\ChromaDB\ChromaDB\Embeddings\OllamaEmbeddingFunction;

/**
 * Class GenerateRAGController.
 */
class GenerateRAGController {

  /**
   * Function test.
   */
  public function test() {
    try {

      $chromaDB = ChromaDB::factory()
        ->withHost('http://cd.straypacket.com')
        ->withPort(80)
        ->connect();

      $version = $chromaDB->version();

      $output = "<h2>ChromaDB Version: $version</h2>";

      return new Response($output);
    }
    catch (\Exception $e) {
      return new Response('An error occured: ' . $e->getMessage(), 500);
    }
  }

}
