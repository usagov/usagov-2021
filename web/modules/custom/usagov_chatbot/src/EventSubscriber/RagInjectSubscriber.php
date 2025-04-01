<?php

namespace Drupal\usagov_chatbot\EventSubscriber;

use Drupal\ai\Event\PreGenerateResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Injects relevant documents into the chatbot request using RAG.
 */
class RagInjectSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The pre generate response event.
   */
  public static function getSubscribedEvents() {
    return [
      PreGenerateResponseEvent::EVENT_NAME => 'injectRagContext',
    ];
  }

  /**
   * Injects retrieved documents from ChromaDB into the chatbot request.
   */
  public function injectRagContext(PreGenerateResponseEvent $event) {
    // Ensure it only modifies chatbot requests.
    if ($event->getOperationType() === 'chat') {
      $messages = $event->getInput()->getMessages();
      $last_message = end($messages);
      $user_query = $last_message->getText();

      // Call the Python script to fetch relevant context from ChromaDB.
      // shell_exec('
      //   python3 -m ensurepip --default pip
      //   python3 -m pip install ollama
      //   python3 -m pip install --upgrade chromadb
      // ');

      $related_docs = shell_exec("python3 " . 'modules/custom/usagov_chatbot/chatbot_rag.py ' . escapeshellarg($user_query));

      // Prepend retrieved context to the user query.
      $new_input_text = "Use only the following context:\n\n" . $related_docs . "\n\nUser Query: " . $user_query;
      $last_message->setText($new_input_text);

    }
  }

}
