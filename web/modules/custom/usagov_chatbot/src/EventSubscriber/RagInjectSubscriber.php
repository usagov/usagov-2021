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
      $python_path = 'chatbot_rag.py';

      // Run the python script.
      // $related_docs = shell_exec("
      //   cd modules/custom/usagov_chatbot/src/EventSubscriber/scripts
      //   sh ./setup.sh $python_path $user_query
      // ");

      // Add debug logging.
      \Drupal::logger('content_entity_example')->notice('@type: deleted %title.',
        array(
          '@type' => "chatbot",
          '%title' => "Chatbot Log",
        ));
      // Prepend retrieved context to the user query.
      // $new_input_text = "Use only the following context:\n\n" . $related_docs . "\n\nUser Query: " . $user_query;

      // $last_message->setText($new_input_text);
    }
  }

}
