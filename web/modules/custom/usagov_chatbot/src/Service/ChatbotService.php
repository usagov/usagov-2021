<?php

namespace Drupal\usagov_chatbot\Service;

use Codewithkyrian\ChromaDB\ChromaDB;
use Codewithkyrian\ChromaDB\Embeddings\OllamaEmbeddingFunction;
use ArdaGnsrn\Ollama\Ollama;
use Drupal\Core\StreamWrapper\PublicStream;

/**
 * Service to interact with ChromaDB and Ollama for chatbot functionality.
 */
class ChatbotService {

  protected $chroma;
  protected $ollama;
  protected $chromaHost = 'https://cd.straypacket.com';
  protected $chromaPort = 443;
  protected $ollamaHost = 'https://ob.straypacket.com';

  public function __construct() {
    $this->chroma = ChromaDB::factory()
      ->withHost($this->chromaHost)
      ->withPort($this->chromaPort)
      ->connect();

    $this->ollama = Ollama::client($this->ollamaHost);
  }

  /**
   * List all Ollama models.
   */
  public function listModels() {
    $models = [];
    $ollamaModels = $this->ollama->models()->list()->toArray();
    foreach ($ollamaModels as $resp) {
      foreach ($resp as $model) {
        $models[] = [
          'name' => $model['name'],
          'size' => $model['size'],
          'updated' => $model['modified_at'],
        ];
      }
    }
    return $models;
  }

  /**
   * List all ChromaDB collections.
   */
  public function listCollections() {
    $collections = [];
    $all = $this->chroma->listCollections();
    foreach ($all as $collection) {
      $collections[] = [
        'name' => $collection->name,
        'id' => $collection->id ?? '',
      ];
    }
    return $collections;
  }

  /**
   * Ask a question using the chat pipeline.
   */
  public function askChat($collectionName, $query, $toJSON = FALSE) {
    $collection = $this->chroma->getCollection($collectionName);

    // Get embedding for the query.
    $queryEmbed = $this->ollama->embed();
    $embedResponse = $queryEmbed->create([
      'model' => 'nomic-embed-text:latest',
      'input' => [$query],
    ])->toArray();
    $embeddings = $embedResponse['embeddings'];

    // Search for similar embeddings.
    $queryResponse = $collection->query(
      queryEmbeddings: $embeddings
    );
    $relateddocs = $queryResponse->ids[0] ?? [];

    $jsonInstructions = '';
    if ($toJSON === TRUE) {
      $jsonInstructions =
        "You must format the answer as a JSON array, with the the information from each resource as an element in the array. " .
        "Do not include any explanatory text outside of the JSON array - the output should only contain the JSON array. ";
    }

    $prompt =
      "{$query}. - Answer that question using ONLY the resources provided. " .
      "If the query is not in the form of a question, prefix the query with \"Tell me about \". " .
      $jsonInstructions .
      "You must include the following information, if the information is present, about each resource: " .
      "name, description, telephone number, email and URL. " .
      "Please avoid saying things similar to 'not enough data' and 'there is no further information'. " .
      "Do not admit ignorance of other data, even if there is more data available, outside of the resources provided. " .
      "You must keep the answer factual, and avoid superlatives or unnecessary adjectives. " .
      "Do not provide any data, or make any suggestions unless it comes from the resources provided. " .
      "The resources to use in your answer are these: " .
      implode(', ', $relateddocs) . ".";

    $completions = $this->ollama->completions()->create([
      'model' => 'llama3.2',
      'prompt' => $prompt,
    ]);

    return [
      'completions' => $completions,
      'related_docs' => $relateddocs,
    ];
  }

  /**
   * Embed all .dat files in the output directory into ChromaDB.
   */
  public function embedSite($collectionName = 'usagovsite', $chunkSize = 100) {
    $embeddingFunction = new OllamaEmbeddingFunction(
      baseUrl: $this->ollamaHost,
      model: 'nomic-embed-text'
    );

    // Optionally delete and recreate collection.
    try {
      $this->chroma->deleteCollection($collectionName);
    }
    catch (\Exception $e) {
      // Ignore if not exists.
    }

    $collection = $this->chroma->getOrCreateCollection(
      $collectionName,
      ['hnsw:space' => 'cosine'],
      $embeddingFunction
    );

    $textDocsPath = PublicStream::basePath() . '/output';
    $textData = $this->readTextFiles($textDocsPath);

    foreach ($textData as $filename => $text) {
      $chunks = $this->chunkSplitter($text, $chunkSize);
      $ids = [];
      $metadatas = [];
      foreach (array_keys($chunks) as $i) {
        $ids[] = $filename . $i;
        $metadatas[] = ['source' => $filename];
      }
      $collection->add(
        ids: $ids,
        documents: $chunks,
        metadatas: $metadatas
      );
    }
    return TRUE;
  }

  // --- Utility methods ---

  /**
   * Read all .dat files from a specified directory.
   *
   * @param string $path
   *   The path to the directory containing .dat files.
   *
   * @return array
   *   An associative array of filename => content pairs.
   */
  protected function readTextFiles($path) {
    $textContents = [];
    foreach (scandir($path) as $filename) {
      if (str_ends_with($filename, '.dat')) {
        $content = file_get_contents($path . DIRECTORY_SEPARATOR . $filename);
        $textContents[$filename] = $content;
      }
    }
    return $textContents;
  }

  /**
   * Split text into chunks of a specified size.
   *
   * @param string $text
   *   The text to split.
   * @param int $chunkSize
   *   The number of words per chunk.
   *
   * @return array
   *   An array of text chunks.
   */
  protected function chunkSplitter($text, $chunkSize = 100) {
    $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
    $chunks = [];
    $current = [];
    foreach ($words as $word) {
      $current[] = $word;
      if (count($current) >= $chunkSize) {
        $chunks[] = implode(' ', $current);
        $current = [];
      }
    }
    if ($current) {
      $chunks[] = implode(' ', $current);
    }
    return $chunks;
  }

}
