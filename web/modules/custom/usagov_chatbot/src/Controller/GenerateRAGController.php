<?php

namespace Drupal\usagov_chatbot\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class GenerateRAGController.
 */
class GenerateRAGController {

  /**
   * Handles the request to get AI response.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object containing user message.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response containing AI-generated completions.
   */
  public function getAiResponse(Request $request) {

    $userMessage = json_decode($request->getContent())->userMessage;
    try {
      $chatbotService = \Drupal::service('usagov_chatbot.chatbot_service') ?? NULL;
      if ($chatbotService === NULL) {
        return new Response('Chatbot service is not available.', 500);
      }
      $modelResponseData = $chatbotService->askChat('usagovsite', $userMessage);
      $completions = $modelResponseData['completions'] ?? [];

      return new Response(json_encode($completions));
    }
    catch (\Exception $e) {
      return new Response('An error occured: ' . $e->getMessage(), 500);
    }
  }

}
