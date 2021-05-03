<?php

namespace Drupal\tome_static;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Site\Settings;
use Drupal\tome_base\PathTrait;
use Drupal\tome_static\Event\CollectPathsEvent;
use Drupal\tome_static\Event\FileSavedEvent;
use Drupal\tome_static\Event\ModifyDestinationEvent;
use Drupal\tome_static\Event\ModifyHtmlEvent;
use Drupal\tome_static\Event\PathPlaceholderEvent;
use Drupal\tome_static\Event\TomeStaticEvents;
use Drupal\tome_static\EventSubscriber\ExcludePathSubscriber;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Drupal\Core\File\FileSystemInterface;

/**
 * Handles static site generation.
 *
 * @internal
 */
class StaticGenerator implements StaticGeneratorInterface {

  use PathTrait;

  /**
   * The HTTP kernel.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $httpKernel;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The static cache.
   *
   * @var \Drupal\tome_static\StaticCacheInterface
   */
  protected $cache;

  /**
   * The account switcher.
   *
   * @var \Drupal\Core\Session\AccountSwitcherInterface
   */
  protected $accountSwitcher;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Creates a StaticGenerator object.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $http_kernel
   *   The HTTP kernel.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\tome_static\StaticCacheInterface $cache
   *   The static cache.
   * @param \Drupal\Core\Session\AccountSwitcherInterface $account_switcher
   *   The account switcher.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system.
   */
  public function __construct(HttpKernelInterface $http_kernel, RequestStack $request_stack, EventDispatcherInterface $event_dispatcher, StaticCacheInterface $cache, AccountSwitcherInterface $account_switcher, FileSystemInterface $file_system) {
    $this->httpKernel = $http_kernel;
    $this->currentRequest = $request_stack->getCurrentRequest();
    $this->eventDispatcher = $event_dispatcher;
    $this->cache = $cache;
    $this->accountSwitcher = $account_switcher;
    $this->requestStack = $request_stack;
    $this->fileSystem = $file_system;

  }

  /**
   * {@inheritdoc}
   */
  public function getPaths() {
    $this->accountSwitcher->switchTo(new AnonymousUserSession());
    $event = new CollectPathsEvent([]);
    $this->eventDispatcher->dispatch(TomeStaticEvents::COLLECT_PATHS, $event);
    $paths = $event->getPaths();

    $paths = $this->cache->filterUncachedPaths($this->currentRequest->getSchemeAndHttpHost(), $paths);
    $this->accountSwitcher->switchBack();
    return array_values($paths);
  }

  /**
   * {@inheritdoc}
   */
  public function cleanupStaticDirectory() {
    foreach ($this->cache->getExpiredFiles() as $file) {
      $this->fileSystem->delete($file);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function requestPath($path) {
    $this->accountSwitcher->switchTo(new AnonymousUserSession());
    $invoke_paths = [];

    $original_path = $path;

    $event = new PathPlaceholderEvent($path);
    $this->eventDispatcher->dispatch(TomeStaticEvents::PATH_PLACEHOLDER, $event);

    if ($event->isInvalid()) {
      $this->accountSwitcher->switchBack();
      return [];
    }

    $path = $event->getPath();

    $request = Request::create($path, 'GET', [], [], [], $this->currentRequest->server->all());

    $request->attributes->set(static::REQUEST_KEY, static::REQUEST_KEY);

    $previous_stack = $this->replaceRequestStack($request);

    try {
      $response = $this->httpKernel->handle($request, HttpKernelInterface::MASTER_REQUEST);
    }
    catch (\Exception $e) {
      $this->accountSwitcher->switchBack();
      $this->restoreRequestStack($previous_stack);
      throw $e;
    }

    $destination = $this->getDestination($path);
    if ($response->isRedirection() || $response->isOk()) {
      $directory = dirname($destination);
      $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
      // This is probably an image style derivative.
      if ($response instanceof BinaryFileResponse) {
        $file_path = $response->getFile()->getPathname();
        $this->copyPath($file_path, $destination);
      }
      else {
        $content = $response->getContent();
        if (strpos($response->headers->get('Content-Type'), 'text/html') === 0) {
          $event = new ModifyHtmlEvent($content, $path);
          $this->eventDispatcher->dispatch(TomeStaticEvents::MODIFY_HTML, $event);
          $content = $event->getHtml();
          $invoke_paths = array_merge($invoke_paths, $this->getHtmlAssets($content, $path), $event->getInvokePaths());
          $invoke_paths = array_diff($invoke_paths, $event->getExcludePaths());
        }
        file_put_contents($destination, $content);
      }
      $this->eventDispatcher->dispatch(TomeStaticEvents::FILE_SAVED, new FileSavedEvent($destination));

      if ($response instanceof RedirectResponse) {
        $target_url = $this->makeExternalUrlLocal($response->getTargetUrl());
        if (!UrlHelper::isExternal($target_url)) {
          $invoke_paths[] = $target_url;
        }
      }

      $this->cache->setCache($request, $response, $original_path, $destination);
    }

    $this->restoreRequestStack($previous_stack);
    $this->accountSwitcher->switchBack();
    return $this->filterInvokePaths($invoke_paths, $request);
  }

  /**
   * {@inheritdoc}
   */
  public function exportPaths(array $paths) {
    $paths = array_diff($paths, $this->getExcludedPaths());
    $paths = array_values(array_unique($paths));

    $invoke_paths = [];

    foreach ($paths as $path) {
      $path = $this->makeExternalUrlLocal($path);
      if (UrlHelper::isExternal($path)) {
        continue;
      }
      $destination = $this->getDestination($path);

      $sanitized_path = $this->sanitizePath($path);
      if ($this->copyPath($sanitized_path, $destination)) {
        if (pathinfo($destination, PATHINFO_EXTENSION) === 'css') {
          $css_assets = $this->getCssAssets(file_get_contents($destination), $sanitized_path);
          $invoke_paths = array_merge($invoke_paths, $this->exportPaths($css_assets));
        }
      }
      else {
        $invoke_paths[] = $path;
      }
    }

    return $this->filterInvokePaths($invoke_paths, $this->currentRequest);
  }

  /**
   * {@inheritdoc}
   */
  public function getStaticDirectory() {
    return Settings::get('tome_static_directory', '../html');
  }

  /**
   * {@inheritdoc}
   */
  public function prepareStaticDirectory() {
    $directory = $this->getStaticDirectory();
    if ($this->cache->isCacheEmpty()) {
      if (file_exists($directory)) {
        try {
          $this->fileSystem->deleteRecursive($directory);
        }
        catch (FileException $e) {
          return FALSE;
        }
      }
    }
    try {
      $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
    }
    catch (FileException $e) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Filters invoke paths to remove any external or cached paths.
   *
   * @param array $invoke_paths
   *   An array of paths returned by requestPath or exportPaths.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   A request object.
   *
   * @return array
   *   An array of paths to invoke.
   */
  protected function filterInvokePaths(array $invoke_paths, Request $request) {
    $invoke_paths = preg_replace(['/^#.*/', '/#.*/'], ['/', ''], $invoke_paths);

    foreach ($invoke_paths as $i => &$invoke_path) {
      $invoke_path = $this->makeExternalUrlLocal($invoke_path);
      if (UrlHelper::isExternal($invoke_path) || strpos($invoke_path, 'data:') === 0) {
        unset($invoke_paths[$i]);
        continue;
      }
      $components = parse_url($invoke_path);
      if (isset($components['query'])) {
        parse_str($components['query'], $query);
        if (isset($query['destination'])) {
          unset($query['destination']);
        }
        $query = http_build_query($query);
        $invoke_path = $components['path'];
        if (!empty($query)) {
          $invoke_path .= '?' . $query;
        }
      }
    }

    $invoke_paths = $this->cache->filterUncachedPaths($request->getSchemeAndHttpHost(), $invoke_paths);

    return array_values(array_unique($invoke_paths));
  }

  /**
   * Makes external URLs local if their hostname is the current hostname.
   *
   * @param string $path
   *   A path.
   *
   * @return string
   *   A possibly transformed path.
   */
  protected function makeExternalUrlLocal($path) {
    $components = parse_url($path);
    if (UrlHelper::isExternal($path) && isset($components['host']) && UrlHelper::externalIsLocal($path, $this->currentRequest->getSchemeAndHttpHost())) {
      $path = $components['path'];
      if (!empty($components['query'])) {
        $path .= '?' . $components['query'];
      }
    }
    return $path;
  }

  /**
   * Finds assets for the given CSS content.
   *
   * @param string $content
   *   A CSS string.
   * @param string $root
   *   A root path to resolve relative paths.
   *
   * @return array
   *   An array of paths found in the given CSS string.
   */
  protected function getCssAssets($content, $root) {
    $paths = [];
    // Regex copied from the Static module from Drupal 7.
    // Credit to Randall Knutson and Michael Vanetta.
    $matches = [];
    preg_match_all('/url\(\s*[\'"]?(?!(?:data)+:)([^\'")]+)[\'"]?\s*\)/i', $content, $matches);
    if (isset($matches[1])) {
      $paths = $matches[1];
    }
    $paths = $this->getRealPaths($paths, $root);
    return $paths;
  }

  /**
   * Turns relative paths into absolute paths.
   *
   * Useful specifically for CSS's url().
   *
   * @param array $paths
   *   An array of paths to convert.
   * @param string $root
   *   A root path to resolve relative paths.
   *
   * @return array
   *   An array of converted paths.
   */
  protected function getRealPaths(array $paths, $root) {
    $root_dir = dirname($this->sanitizePath($root));
    foreach ($paths as &$path) {
      if (strpos($path, '../') !== FALSE) {
        $path = $this->joinPaths($root_dir, $path);
      }
    }
    return $paths;
  }

  /**
   * Finds and exports assets for the given HTML content.
   *
   * @param string $content
   *   An HTML string.
   * @param string $root
   *   A root path to resolve relative paths.
   *
   * @return array
   *   An array of paths found in the given HTML string.
   */
  protected function getHtmlAssets($content, $root) {
    $paths = [];
    $document = new \DOMDocument();
    @$document->loadHTML($content);
    $xpath = new \DOMXPath($document);
    /** @var \DOMElement $image */
    foreach ($xpath->query('//img | //source | //video') as $image) {
      if ($image->hasAttribute('src')) {
        $paths[] = $image->getAttribute('src');
      }
      if ($image->hasAttribute('poster')) {
        $paths[] = $image->getAttribute('poster');
      }
      if ($image->hasAttribute('srcset')) {
        $srcset = $image->getAttribute('srcset');
        $sources = explode(' ', preg_replace('/ [^ ]+(,|$)/', '', $srcset));
        foreach ($sources as $src) {
          $paths[] = $src;
        }
      }
    }
    /** @var \DOMElement $node */
    foreach ($xpath->query('//svg/use') as $node) {
      if ($node->hasAttribute('xlink:href')) {
        $paths[] = $node->getAttribute('xlink:href');
      }
    }
    $rels = [
      'stylesheet',
      'shortcut icon',
      'icon',
      'image_src',
    ];
    /** @var \DOMElement $node */
    foreach ($document->getElementsByTagName('link') as $node) {
      if (in_array($node->getAttribute('rel'), $rels, TRUE) && $node->hasAttribute('href')) {
        $paths[] = $node->getAttribute('href');
      }
    }
    $meta_files = [
      'twitter:image',
      'twitter:player:stream',
      'og:image',
      'og:video',
      'og:audio',
      'og:image:url',
      'og:image:secure_url',
    ];
    /** @var \DOMElement $node */
    foreach ($document->getElementsByTagName('meta') as $node) {
      if ((in_array($node->getAttribute('property'), $meta_files, TRUE) || in_array($node->getAttribute('name'), $meta_files, TRUE)) && $node->hasAttribute('content')) {
        $paths[] = $node->getAttribute('content');
      }
    }
    /** @var \DOMElement $node */
    foreach ($document->getElementsByTagName('a') as $node) {
      if ($node->hasAttribute('href')) {
        $paths[] = $node->getAttribute('href');
      }
    }
    /** @var \DOMElement $node */
    foreach ($document->getElementsByTagName('script') as $node) {
      if ($node->hasAttribute('src')) {
        $paths[] = $node->getAttribute('src');
      }
    }
    /** @var \DOMElement $node */
    foreach ($document->getElementsByTagName('style') as $node) {
      $paths = array_merge($paths, $this->getCssAssets($node->textContent, $root));
    }
    foreach ($xpath->query('//*[@style]') as $node) {
      $paths = array_merge($paths, $this->getCssAssets($node->getAttribute('style'), $root));
    }
    /** @var \DOMElement $node */
    foreach ($document->getElementsByTagName('iframe') as $node) {
      if ($node->hasAttribute('src')) {
        $paths[] = $node->getAttribute('src');
      }
    }

    // Recursive call in HTML comments in order to retrieve conditional assets.
    /** @var \DOMElement $node */
    foreach ($xpath->query('//comment()') as $node) {
      $paths = array_merge($paths, $this->getHtmlAssets($node->nodeValue, $root));
    }

    return $paths;
  }

  /**
   * Returns a destination for saving a given path.
   *
   * @param string $path
   *   The path.
   *
   * @return string
   *   The destination.
   */
  protected function getDestination($path) {
    $event = new ModifyDestinationEvent($path);
    $this->eventDispatcher->dispatch(TomeStaticEvents::MODIFY_DESTINATION, $event);
    $path = $event->getDestination();
    $path = urldecode($path);
    $path = $this->sanitizePath($path);
    if (empty(pathinfo($path, PATHINFO_EXTENSION))) {
      $path .= '/index.html';
    }
    return $this->joinPaths($this->getStaticDirectory(), $path);
  }

  /**
   * Sanitizes a given path by removing hashes, get params, and extra slashes.
   *
   * @param string $path
   *   The path.
   *
   * @return string
   *   The sanitized path.
   */
  protected function sanitizePath($path) {
    $path = preg_replace(['/\?.*/', '/#.*/'], '', $path);
    return ltrim($path, '/');
  }

  /**
   * Attempts to copy a path from the file system.
   *
   * @param string $path
   *   The path.
   * @param string $destination
   *   The destination.
   *
   * @return bool
   *   TRUE if $path exists and was copied to $destination, FALSE otherwise.
   */
  protected function copyPath($path, $destination) {
    $path = urldecode($path);

    $base_path = base_path();
    if ($base_path !== '/') {
      $base_path = ltrim($base_path, '/');
      $pattern = '|^' . preg_quote($base_path, '|') . '|';
      $path = preg_replace($pattern, '', $path);
    }
    if (file_exists($path)) {
      $directory = dirname($destination);
      $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
      $this->fileSystem->copy($path, $destination, FileSystemInterface::CREATE_DIRECTORY);
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Returns paths excluded globally and per site.
   *
   * @return array
   *   An array of excluded paths.
   */
  protected function getExcludedPaths() {
    $paths = ExcludePathSubscriber::getExcludedPaths();
    foreach ($paths as &$path) {
      $path = $this->joinPaths(base_path(), $path);
    }
    return $paths;
  }

  /**
   * Replaces the request stack with a static request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The new static request.
   *
   * @return \Symfony\Component\HttpFoundation\Request[]
   *   An array of previous stack requests.
   */
  protected function replaceRequestStack(Request $request) {
    $previous_stack = [];
    while ($pop = $this->requestStack->pop()) {
      $previous_stack[] = $pop;
    };
    $this->requestStack->push($request);
    return array_reverse($previous_stack);
  }

  /**
   * Restores the request stack to its previous state.
   *
   * @param \Symfony\Component\HttpFoundation\Request[] $stack
   *   An array of previous stack requests.
   */
  protected function restoreRequestStack(array $stack) {
    while ($this->requestStack->pop()) {
    };
    foreach ($stack as $request) {
      $this->requestStack->push($request);
    }
  }

}
