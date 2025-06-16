<?php

namespace Drupal\usagov_chatbot\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\Commands\DrushCommands;

/**
 * Class ChatbotCommands.
 *
 * @package Drupal\usagov_chatbot\Commands
 */
class ChatbotCommands extends DrushCommands {

  /**
   * @var \Drupal\usagov_chatbot\Service\ChatbotService
   */
  protected $chatbotService;

  /**
   * ChatbotCommands constructor.
   */
  public function __construct() {
    parent::__construct();
    $this->chatbotService = \Drupal::service('usagov_chatbot.chatbot_service');
  }

  /**
   * List Ollama models.
   *
   * @command usagov_chatbot:listModels
   * @aliases listModels
   */
  public function listModels($options = ['format' => 'json']) {
    $output_array = [];
    try {
      $models = $this->chatbotService->listModels();
      foreach ($models as $model) {
        $output_array[] = [$model['name'], $model['size'], $model['updated']];
      }
    }
    catch (\Exception $e) {
      $output_array[] = [$e->getMessage()];
    }
    return $output_array;
  }

  /**
   * List ChromaDB collections.
   *
   * @command usagov_chatbot:listCollections
   * @aliases listCollections
   */
  public function listCollections($options = ['format' => 'json']) {
    $output_array = [];
    try {
      $collections = $this->chatbotService->listCollections();
      foreach ($collections as $collection) {
        $output_array[] = [$collection['name'], $collection['id']];
      }
    }
    catch (\Exception $e) {
      $output_array = ['message' => $e->getMessage(), 'status' => 1];
    }
    return $output_array;
  }

  /**
   * Ask a question using the chat pipeline.
   *
   * @command usagov_chatbot:askChat
   * @aliases askChat
   */
  public function askChat($collectionName, $query, $options = ['format' => 'json']) {
    try {
      $result = $this->chatbotService->askChat($collectionName, $query, TRUE);
      return $result['completions']->response;
    }
    catch (\Exception $e) {
      return [['error' => $e->getMessage()]];
    }
  }

  /**
   * Embed all .dat files in the output directory into ChromaDB.
   *
   * @command usagov_chatbot:embedSite
   * @aliases embedSite
   */
  public function embedSite($collectionName = 'usagovsite', $chunkSize = 100, $options = ['format' => 'json']) {
    try {
      $this->chatbotService->embedSite($collectionName, $chunkSize);
      return ['message' => 'Embedding complete', 'status' => 0];
    }
    catch (\Exception $e) {
      return ['message' => $e->getMessage(), 'status' => 1];
    }
  }

}
