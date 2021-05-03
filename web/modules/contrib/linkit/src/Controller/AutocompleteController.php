<?php

namespace Drupal\linkit\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\linkit\SuggestionManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for linkit autocomplete routes.
 */
class AutocompleteController implements ContainerInjectionInterface {

  /**
   * The linkit profile storage service.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $linkitProfileStorage;

  /**
   * The suggestion manager.
   *
   * @var \Drupal\linkit\SuggestionManager
   */
  protected $suggestionManager;

  /**
   * The linkit profile.
   *
   * @var \Drupal\linkit\ProfileInterface
   */
  protected $linkitProfile;

  /**
   * Constructs a EntityAutocompleteController object.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $linkit_profile_storage
   *   The linkit profile storage service.
   * @param \Drupal\linkit\SuggestionManager $suggestionManager
   *   The suggestion service.
   */
  public function __construct(EntityStorageInterface $linkit_profile_storage, SuggestionManager $suggestionManager) {
    $this->linkitProfileStorage = $linkit_profile_storage;
    $this->suggestionManager = $suggestionManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('linkit_profile'),
      $container->get('linkit.suggestion_manager')
    );
  }

  /**
   * Menu callback for linkit search autocompletion.
   *
   * Like other autocomplete functions, this function inspects the 'q' query
   * parameter for the string to use to search for suggestions.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param string $linkit_profile_id
   *   The linkit profile id.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing the autocomplete suggestions.
   */
  public function autocomplete(Request $request, $linkit_profile_id) {
    $this->linkitProfile = $this->linkitProfileStorage->load($linkit_profile_id);
    $string = $request->query->get('q');

    $suggestionCollection = $this->suggestionManager->getSuggestions($this->linkitProfile, mb_strtolower($string));

    /*
     * If there are no suggestions from the matcher plugins, we have to add a
     * special suggestion that have the same path as the given string so users
     * can select it and use it anyway. This is a common use case with external
     * links.
     */
    if (!count($suggestionCollection->getSuggestions()) && !empty($string)) {
      $suggestionCollection = $this->suggestionManager->addUnscathedSuggestion($suggestionCollection, $string);
    }

    return new JsonResponse($suggestionCollection);
  }

}
