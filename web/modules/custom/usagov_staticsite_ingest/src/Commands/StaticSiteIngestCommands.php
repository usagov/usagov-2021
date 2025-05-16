<?php

namespace Drupal\usagov_staticsite_ingest\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Console\Bootstrap\Drupal;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Formatter\OutputFormatter;

use Symfony\Component\HttpFoundation\Response;
use Codewithkyrian\ChromaDB\ChromaDB;
use Codewithkyrian\ChromaDB\ChromaDB\Embeddings\OllamaEmbeddingFunction;
use ArdaGnsrn\Ollama\Ollama;

/**
 * Class StaticSiteIngestCommands.
 *
 * @package Drupal\usagov_staticsite_ingest\Commands
 */
class StaticSiteIngestCommands extends DrushCommands {

  /**
   * Comment
   *
   * @command \usagov_staticsite_ingest:listCollections
   * @aliases listCollections
   */
  public function listCollections( $options = ['format' => 'table']) {
    $output_array = [];

    try {

      $chromaDB = ChromaDB::factory()
        ->withHost('https://cd.straypacket.com')
        ->withPort(443)
        ->connect();

      $collection = $chromaDB->getCollection('usagovsite');
      // return new Response($collection->id);

      // Connect to Ollama server.
      $ollamaClient = Ollama::client('https://ob.straypacket.com');

      $ollamaModels = $ollamaClient->models()->list()->toArray();
      foreach ( $ollamaModels as $resp) {
        foreach ( $resp as $model ) {
          //print var_dump($resp);
          $name = $model['name'];
          $size = $model['size'];
          $updated = $model['modified_at'];
          $output_array[] = [ $name,$size,$updated ];
          print( "$name,$size,$updated\n");
        }
      }

    }
    catch (\Exception $e) {
      print("An error occurred: " . $e->getMessage());
      //return new Response('An error occured: ' . $e->getMessage(), 500);
      $output_array[] = $e->getMessage();
    }

    return new RowsOfFields($output_array);
  }

  /**
   * Comment
   *
   * @command \usagov_staticsite_ingest:generateQuery
   * @aliases generateQuery
   */
  public function generateQuery( $collectionName, $query, $options = ['format' => 'table']) {
    $output_array = [];
    
    try {
      $chromaDB = ChromaDB::factory()
        ->withHost('https://cd.straypacket.com')
        ->withPort(443)
        ->connect();

      $collection = $chromaDB->getCollection($collectionName);

      // Connect to Ollama server.
      $ollamaClient = Ollama::client('https://ob.straypacket.com');

      // Need to change this with the user message.
      //$query = "Please tell me about any services or benefits available to veterans";

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

       $output_array[] = [ json_encode($completions) ];
       //return new Response(json_encode($completions));
    }
    catch (\Exception $e) {
      print("An error occurred: " . $e->getMessage());
      $output_array[] = [ $e->getMessage() ];
    }
   
    return new RowsOfFields($output_array);
  }
  /**
   * Comment
   *
   * @command \usagov_staticsite_ingest:submitEmbedChunk
   * @aliases submitEmbedChunk
   */
  public function submitEmbedChunk( $collectionName, $chunk, $options = ['format' => 'table']) {
    $output_array = [];
    return new RowsOfFields($output_array);
  }

}
