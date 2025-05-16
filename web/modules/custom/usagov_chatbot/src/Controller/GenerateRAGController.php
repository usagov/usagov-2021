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
        ->withHost('https://cd.straypacket.com')
        ->withPort(443)
        ->connect();

      $collection = $chromaDB->getCollection('usagovsite');
      // return new Response($collection->id);

      // Connect to Ollama server.
      $ollamaClient = Ollama::client('https://ob.straypacket.com');

      // Need to change this with the user message.
      //$query = "What is usa.gov?";
      $query = "Please tell me about any services or benefits available to veterans";

      $queryEmbed = $ollamaClient->embed();
      $embedResponse = $queryEmbed->create([
        'model' => 'nomic-embed-text:latest',
        'input' => [
          $query,
        ],
      ])->toArray();
      $embeddings = $embedResponse['embeddings'];

      // Search for similar embeddings.
      $queryResponse = $collection->query(
        queryEmbeddings: $embeddings
        //, nResults: 2
      );

      $relateddocs = $queryResponse->ids[0];
      
      $prompt =
        "{$query} - Answer that question using ONLY the resources provided. " .
        "Please avoid saying things similar to 'not enough data' and 'there is no further information'" .
        "Do not admit ignorance of other data, even if there is more data available, " .
        "outside of the resources provided. " .

        "Please keep the answer factual, and avoid superlatives or unnecessary adjectives." .

        "Do not provide any data, or make any suggestions unless it comes from the " .
        "following resources: {$relateddocs}.";
      
       $completions = $ollamaClient->completions()->create([
         'model' => 'llama3.2',
         'prompt' => $prompt,
       ]);

       return new Response(json_encode($completions));
    }
    catch (\Exception $e) {
      return new Response('An error occured: ' . $e->getMessage(), 500);
    }
  }

}
