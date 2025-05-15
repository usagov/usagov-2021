<?php

namespace Drupal\usagov_chatbot\Controller;

use Symfony\Component\HttpFoundation\Response;
use Codewithkyrian\ChromaDB\ChromaDB;
use Codewithkyrian\ChromaDB\ChromaDB\Embeddings\OllamaEmbeddingFunction;
use ArdaGnsrn\Ollama\Ollama;

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

      $collection = $chromaDB->getCollection('usagovsite');
      // return new Response($collection->id);

      // Connect to Ollama server.
      $ollamaClient = Ollama::client('http://ob.straypacket.com');

      // $ollamaModels = $ollamaClient->models()->list();
      // return new Response(dump($ollamaModels->toArray()));

      // Need to change this with the user message.
      $query = "What is usa.gov?";

      $queryembed = $ollamaClient->embed()->create([
        'model' => 'nomic-embed-text',
        'input' => [
          $query,
        ],
      ])->embeddings;

      // Search for similar embeddings.
      $queryResponse = $collection->query(
        queryEmbeddings: $queryembed,
        nResults: 2
      );
      return new Response(dump($queryResponse->ids[0][0]));

      // $relateddocs = ;

      // $prompt =
      //   "{$query} - Answer that question using ONLY the resources provided. " .
      //   "Please avoid saying things similar to 'not enough data' and 'there is no further information'" .
      //   "Do not admit ignorance of other data, even if there is more data available, " .
      //   "outside of the resources provided. " .

      //   "Please keep the answer factual, and avoid superlatives or unnecessary adjectives." .

      //   "Do not provide any data, or make any suggestions unless it comes from the " .
      //   "following resources: {$relateddocs}.";

      // $completions = $ollamaClient->completions()->create([
      //   'model' => 'llama3.2',
      //   'prompt' => $prompt,
      // ]);

      // return new Response(dump($completions->response));
    }
    catch (\Exception $e) {
      return new Response('An error occured: ' . $e->getMessage(), 500);
    }
  }

}
