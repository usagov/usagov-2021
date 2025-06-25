<?php

namespace Drupal\usagov_feeds_tamper_featured_image\Plugin\Tamper;

use Drupal\tamper\TamperableItemInterface;
use Drupal\tamper\TamperBase;
use Drupal\media\Entity\Media;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileExists;

/**
 * @Tamper(
 *   id = "featured_image_download",
 *   label = @Translation("Download and attach featured image"),
 *   description = @Translation("Downloads an image from a URL and attaches it as a media entity."),
 *   category = "Other"
 * )
 */
class FeaturedImageDownload extends TamperBase {

  public function tamper($data, ?TamperableItemInterface $item = NULL) {
    if (empty($data) || !filter_var($data, FILTER_VALIDATE_URL)) {
      return NULL;
    }

    // Properly encode the URL to handle spaces and special characters
    $parsed_url = parse_url($data);
    if ($parsed_url === FALSE) {
      return NULL;
    }

    // Rebuild URL with proper encoding - use rawurlencode for path components
    $encoded_url = $parsed_url['scheme'] . '://' . $parsed_url['host'];
    if (isset($parsed_url['port'])) {
      $encoded_url .= ':' . $parsed_url['port'];
    }
    if (isset($parsed_url['path'])) {
      // Split path into segments and encode each one separately
      $path_segments = explode('/', $parsed_url['path']);
      $encoded_segments = array_map('rawurlencode', $path_segments);
      $encoded_url .= implode('/', $encoded_segments);
    }
    if (isset($parsed_url['query'])) {
      $encoded_url .= '?' . $parsed_url['query'];
    }

    // Log the URLs for debugging
    \Drupal::logger('usagov_feeds_tamper_featured_image')->info('Original URL: @original, Encoded URL: @encoded', [
      '@original' => $data,
      '@encoded' => $encoded_url,
    ]);

    // Download the image data using Drupal's HTTP client
    $http_client = \Drupal::httpClient();

    // Try multiple URL variants in case of encoding issues
    $urls_to_try = [$encoded_url, $data];

    foreach ($urls_to_try as $url_attempt) {
      try {
        $response = $http_client->get($url_attempt, [
          'timeout' => 30,
          'verify' => FALSE,
          'headers' => [
            'User-Agent' => 'Mozilla/5.0 (compatible; Drupal)',
            'Accept' => 'image/*,*/*;q=0.8',
          ],
          // Add DNS resolver options to help with resolution issues
          'curl' => [
            CURLOPT_DNS_SERVERS => '8.8.8.8,1.1.1.1',
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
          ],
        ]);

        if ($response->getStatusCode() !== 200) {
          \Drupal::logger('usagov_feeds_tamper_featured_image')->warning('HTTP error @status downloading image from URL: @url', [
            '@status' => $response->getStatusCode(),
            '@url' => $url_attempt,
          ]);
          continue; // Try next URL variant
        }

        $image_data = $response->getBody()->getContents();

        if (empty($image_data)) {
          \Drupal::logger('usagov_feeds_tamper_featured_image')->warning('Empty response downloading image from URL: @url', ['@url' => $url_attempt]);
          continue; // Try next URL variant
        }

        // Success! Break out of the loop
        \Drupal::logger('usagov_feeds_tamper_featured_image')->info('Successfully downloaded image from URL: @url', ['@url' => $url_attempt]);
        break;
      }
      catch (\Exception $e) {
        \Drupal::logger('usagov_feeds_tamper_featured_image')->warning('Failed to download image from URL: @url. Error: @error', [
          '@url' => $url_attempt,
          '@error' => $e->getMessage(),
        ]);

        // If this is the last attempt, return NULL
        if ($url_attempt === end($urls_to_try)) {
          return NULL;
        }
        // Otherwise continue to next URL variant
      }
    }

    // If we get here and don't have image_data, all attempts failed
    if (!isset($image_data) || empty($image_data)) {
      return NULL;
    }

    // Prepare directory and filename
    $directory = 'public://featured_images';
    $filename = basename($parsed_url['path'] ?? 'image');

    // Ensure we have a valid filename
    if (empty($filename) || !pathinfo($filename, PATHINFO_EXTENSION)) {
      // Try to detect image type from content
      $finfo = new \finfo(FILEINFO_MIME_TYPE);
      $mime_type = $finfo->buffer($image_data);
      $extension = '.jpg'; // default
      if ($mime_type === 'image/jpeg') {
        $extension = '.jpg';
      }
      elseif ($mime_type === 'image/png') {
        $extension = '.png';
      }
      elseif ($mime_type === 'image/gif') {
        $extension = '.gif';
      }
      elseif ($mime_type === 'image/webp') {
        $extension = '.webp';
      }
      $filename = 'image_' . time() . $extension;
    }

    \Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $destination = $directory . '/' . $filename;

    // Save the file
    $file = \Drupal::service('file.repository')->writeData($image_data, $destination, FileExists::Rename);

    if ($file === NULL) {
      \Drupal::logger('usagov_feeds_tamper_featured_image')->error('Failed to save file: @destination', ['@destination' => $destination]);
      return NULL;
    }

    // Create a media entity for the file
    $media = Media::create([
      'bundle' => 'image',
      'name' => pathinfo($filename, PATHINFO_FILENAME),
      'field_media_image' => [
        'target_id' => $file->id(),
        'alt' => 'Featured image',
      ],
      'uid' => 1, // Set to admin user or appropriate user
      'status' => 1,
    ]);

    $media->save();

    // Return the media entity ID
    return $media->id();
  }

}
