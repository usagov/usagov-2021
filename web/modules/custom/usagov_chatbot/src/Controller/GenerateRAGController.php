<?php

namespace Drupal\usagov_chatbot\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Codewithkyrian\ChromaDB\ChromaDB;
use ArdaGnsrn\Ollama\Ollama;

/**
 * Class GenerateRAGController.
 */
class GenerateRAGController {

  /**
   * Function test.
   */
  public function GetAIResponse(Request $request) {

    $userMessage = json_decode($request->getContent())->userMessage;

    try {

      $chromaDB = ChromaDB::factory()
        ->withHost('https://cd.straypacket.com')
        ->withPort(443)
        ->connect();

      $collection = $chromaDB->getCollection('usagovsite');

      // Connect to Ollama server.
      $ollamaClient = Ollama::client('https://ob.straypacket.com');

      $queryEmbed = $ollamaClient->embed();
      $embedResponse = $queryEmbed->create([
        'model' => 'nomic-embed-text:latest',
        'input' => [
          $userMessage,
        ],
      ])->toArray();
      $embeddings = $embedResponse['embeddings'];

      // Search for similar embeddings.
      $queryResponse = $collection->query(
        queryEmbeddings: $embeddings
      );

      $relateddocs = $queryResponse->ids[0];

      $prompt =
        "{$userMessage} - Answer that question using ONLY the resources provided. " .
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
