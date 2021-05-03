<?php

namespace Drupal\Tests\metatag_open_graph\Functional;

use Drupal\Tests\metatag\Functional\MetatagTagsTestBase;

/**
 * Tests that each of the Metatag Open Graph tags work correctly.
 *
 * @group metatag
 */
class MetatagOpenGraphTagsTest extends MetatagTagsTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['metatag_open_graph'];

  /**
   * {@inheritdoc}
   */
  protected $tags = [
    'article_author',
    'article_expiration_time',
    'article_modified_time',
    'article_published_time',
    'article_publisher',
    'article_section',
    'article_tag',
    'book_author',
    'book_isbn',
    'book_release_date',
    'book_tag',
    'og_audio',
    'og_audio_secure_url',
    'og_audio_type',
    'og_country_name',
    'og_description',
    'og_determiner',
    'og_email',
    'og_fax_number',
    'og_image_alt',
    'og_image_height',
    'og_image_type',
    'og_image_width',
    'og_latitude',
    'og_locale',
    'og_locale_alternative',
    'og_locality',
    'og_longitude',
    'og_phone_number',
    'og_postal_code',
    'og_region',
    'og_see_also',
    'og_site_name',
    'og_street_address',
    'og_title',
    'og_type',
    'og_updated_time',
    'og_video_height',
    'og_video_type',
    'og_video_width',
    'og_video_duration',
    'profile_first_name',
    'profile_gender',
    'profile_last_name',
    'profile_username',
    'video_actor',
    'video_actor_role',
    'video_director',
    'video_release_date',
    'video_series',
    'video_tag',
    'video_writer',
    // @todo Fix these.
    // 'og_image',
    // 'og_image_secure_url',
    // 'og_image_url',
    // 'og_url',
    // 'og_video',
    // 'og_video_secure_url',
  ];

  /**
   * {@inheritdoc}
   */
  protected $testTag = 'meta';

  /**
   * {@inheritdoc}
   */
  protected $testNameAttribute = 'property';

  /**
   * Each of these meta tags has a different tag name vs its internal name.
   */
  protected function getTestTagName($tag_name) {
    if ($tag_name == 'og_latitude') {
      $tag_name = 'place:location:latitude';
    }
    elseif ($tag_name == 'og_longitude') {
      $tag_name = 'place:location:longitude';
    }

    // Replace the first underline with a colon.
    $tag_name = str_replace('og_', 'og:', $tag_name);
    $tag_name = str_replace('article_', 'article:', $tag_name);
    $tag_name = str_replace('book_', 'book:', $tag_name);
    $tag_name = str_replace('profile_', 'profile:', $tag_name);
    $tag_name = str_replace('video_', 'video:', $tag_name);

    // Some tags have an additional underline that turns into a colon.
    $tag_name = str_replace('og:audio_', 'og:audio:', $tag_name);
    $tag_name = str_replace('og:image_', 'og:image:', $tag_name);
    $tag_name = str_replace('og:video_', 'og:video:', $tag_name);
    $tag_name = str_replace('video:actor_', 'video:actor:', $tag_name);

    // Additional fixes.
    if ($tag_name == 'og:locale_alternative') {
      $tag_name = 'og:locale:alternate';
    }

    return $tag_name;
  }

  /**
   * Implements TestFieldXpath() callback for og_description.
   */
  protected function ogDescriptionTestFieldXpath() {
    return '//textarea[@name="og_description"]';
  }

}
