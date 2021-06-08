<?php

namespace Drupal\Tests\webform\Functional;

use Drupal\Core\Serialization\Yaml;
use Drupal\webform\Entity\Webform;

/**
 * Tests for webform translation.
 *
 * @group webform
 */
class WebformEntityTranslationTest extends WebformBrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['block', 'filter', 'webform', 'webform_ui', 'webform_test_translation'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Place blocks.
    $this->placeBlocks();

    // Create filters.
    $this->createFilters();
  }

  /**
   * Tests settings translate.
   */
  public function testSettingsTranslate() {
    // Login admin user.
    $this->drupalLogin($this->rootUser);

    $this->drupalGet('/admin/structure/webform/config/translate/fr/add');

    // Check custom HTML source and translation.
    $mail_default_body_html = \Drupal::config('webform.settings')->get('mail.default_body_html');
    $this->assertRaw('<span lang="en">' . $mail_default_body_html . '</span>');
    $this->assertCssSelect('textarea.js-html-editor[name="translation[config_names][webform.settings][settings][default_form_open_message][value]"]');

    // Check custom YAML source and translation.
    $this->assertCssSelect('#edit-source-config-names-webformsettings-test-types textarea.js-webform-codemirror.yaml');
    $this->assertCssSelect('textarea.js-webform-codemirror.yaml[name="translation[config_names][webform.settings][test][types]"]');

    // Check custom plain text source and translation.
    $this->assertCssSelect('#edit-source-config-names-webformsettings-mail-default-body-text textarea.js-webform-codemirror.text');
    $this->assertCssSelect('textarea.js-webform-codemirror.text[name="translation[config_names][webform.settings][mail][default_body_text]"]');
  }

  /**
   * Tests webform translate.
   */
  public function testWebformTranslate() {
    // Login admin user.
    $this->drupalLogin($this->rootUser);

    // Set [site:name] to 'Test Website' and translate it into Spanish.
    $this->drupalPostForm('/admin/config/system/site-information', ['site_name' => 'Test Website'], 'Save configuration');
    $this->drupalPostForm('/admin/config/system/site-information/translate/es/add', ['translation[config_names][system.site][name]' => 'Sitio web de prueba'], 'Save translation');

    /** @var \Drupal\webform\WebformTranslationManagerInterface $translation_manager */
    $translation_manager = \Drupal::service('webform.translation_manager');

    /** @var \Drupal\webform\WebformInterface $webform */
    $webform = Webform::load('test_translation');
    $elements_raw = \Drupal::config('webform.webform.test_translation')->get('elements');
    $elements = Yaml::decode($elements_raw);

    // Check translate tab.
    $this->drupalGet('/admin/structure/webform/manage/test_translation');
    $this->assertRaw('>Translate<');

    // Check translations.
    $this->drupalGet('/admin/structure/webform/manage/test_translation/translate');
    $this->assertRaw('<a href="' . base_path() . 'webform/test_translation"><strong>English (original)</strong></a>');
    $this->assertRaw('<a href="' . base_path() . 'es/webform/test_translation" hreflang="es">Spanish</a>');
    $this->assertNoRaw('<a href="' . base_path() . 'fr/webform/test_translation" hreflang="fr">French</a>');
    $this->assertRaw('<a href="' . base_path() . 'admin/structure/webform/manage/test_translation/translate/es/edit">Edit</a>');

    // Check Spanish translation.
    $this->drupalGet('/admin/structure/webform/manage/test_translation/translate/es/edit');
    $this->assertFieldByName('translation[config_names][webform.webform.test_translation][title]', 'Prueba: Traducción');

    // Check processed text translation.
    $this->assertFieldByName('translation[config_names][webform.webform.test_translation][elements][processed_text][text][value]', '<p><strong>Algún texto</strong></p>');

    // Check textfield translation.
    $this->assertFieldByName('translation[config_names][webform.webform.test_translation][elements][textfield][title]', 'Campo de texto');

    // Check select with options translation.
    $this->assertFieldByName('translation[config_names][webform.webform.test_translation][elements][select_options][title]', 'Seleccione (opciones)');

    // Check select with custom options translation.
    $this->assertFieldByName('translation[config_names][webform.webform.test_translation][elements][select_custom][title]', 'Seleccione (personalizado)');
    $this->assertFieldByName('translation[config_names][webform.webform.test_translation][elements][select_custom][options][4]', 'Las cuatro');
    $this->assertFieldByName('translation[config_names][webform.webform.test_translation][elements][select_custom][other__option_label]', 'Número personalizado…');

    // Check image select translation.
    $this->assertFieldByName('translation[config_names][webform.webform.test_translation][elements][webform_image_select][title]', 'Seleccionar imagen');
    $this->assertFieldByName('translation[config_names][webform.webform.test_translation][elements][webform_image_select][images][kitten_1][text]', 'Lindo gatito 1');
    $this->assertFieldByName('translation[config_names][webform.webform.test_translation][elements][webform_image_select][images][kitten_1][src]', 'http://placekitten.com/220/200');

    // Check details translation.
    $this->assertFieldByName('translation[config_names][webform.webform.test_translation][elements][details][title]', 'Detalles');

    // Check markup translation.
    $this->assertFieldByName('translation[config_names][webform.webform.test_translation][elements][markup][markup][value]', 'Esto es un poco de marcado HTML.');

    // Check custom composite translation.
    $this->assertFieldByName('translation[config_names][webform.webform.test_translation][elements][composite][title]', 'Compuesto');
    $this->assertFieldByName('translation[config_names][webform.webform.test_translation][elements][composite][element][first_name][title]', 'Nombre');
    $this->assertFieldByName('translation[config_names][webform.webform.test_translation][elements][composite][element][last_name][title]', 'Apellido');
    $this->assertFieldByName('translation[config_names][webform.webform.test_translation][elements][composite][element][age][title]', 'Edad');
    $this->assertFieldByName('translation[config_names][webform.webform.test_translation][elements][composite][element][age][field_suffix]', 'años. antiguo');

    // Check address translation.
    $this->assertFieldByName('translation[config_names][webform.webform.test_translation][elements][address][title]', 'Dirección');
    $this->assertFieldByName('translation[config_names][webform.webform.test_translation][elements][address][address__title]', 'Dirección');

    // Check computed token translation.
    $this->assertFieldByName('translation[config_names][webform.webform.test_translation][elements][computed_token][title]', 'Computado (token)');

    // Check action translation.
    $this->assertFieldByName('translation[config_names][webform.webform.test_translation][elements][actions][title]', 'Enviar botón (s)');
    $this->assertFieldByName('translation[config_names][webform.webform.test_translation][elements][actions][submit__label]', 'Enviar mensaje');

    // Check form builder is not translated.
    $this->drupalGet('/es/admin/structure/webform/manage/test_translation');
    $this->assertLink('Text field');
    $this->assertNoLink('Campo de texto');

    // Check form builder is not translated when reset.
    $this->drupalPostForm('/es/admin/structure/webform/manage/test_translation', [], 'Reset');
    $this->assertLink('Text field');
    $this->assertNoLink('Campo de texto');

    // Check element edit form is not translated.
    $this->drupalGet('/es/admin/structure/webform/manage/test_translation/element/textfield/edit');
    $this->assertFieldByName('properties[title]', 'Text field');
    $this->assertNoFieldByName('properties[title]', 'Campo de texto');

    // Check translated webform options.
    $this->drupalGet('/es/webform/test_translation');
    $this->assertRaw('<label for="edit-textfield">Campo de texto</label>');
    $this->assertRaw('<option value="1">Uno</option>');
    $this->assertRaw('<option value="4">Las cuatro</option>');

    // Check translated webform custom composite.
    $this->drupalGet('/es/webform/test_translation');
    $this->assertRaw('<label>Compuesto</label>');
    $this->assertRaw('<th class="composite-table--first_name webform-multiple-table--first_name">Nombre</th>');
    $this->assertRaw('<th class="composite-table--last_name webform-multiple-table--last_name">Apellido</th>');
    $this->assertRaw('<th class="composite-table--age webform-multiple-table--age">Edad</th>');
    $this->assertRaw('<span class="field-suffix">años. antiguo</span>');

    // Check translated webform address.
    $this->drupalGet('/es/webform/test_translation');
    $this->assertRaw('<span class="visually-hidden fieldset-legend">Dirección</span>');
    $this->assertRaw('<label for="edit-address-address">Dirección</label>');
    $this->assertRaw('<label for="edit-address-address-2">Dirección 2</label>');
    $this->assertRaw('<label for="edit-address-city">Ciudad / Pueblo</label>');
    $this->assertRaw('<label for="edit-address-state-province">Estado / Provincia</label>');
    $this->assertRaw('<label for="edit-address-postal-code">ZIP / Código Postal</label>');
    $this->assertRaw('<label for="edit-address-country">Acciones de país</label>');

    // Check translated webform token.
    $this->assertRaw('Site name: Sitio web de prueba');

    // Check that webform is not translated into French.
    $this->drupalGet('/fr/webform/test_translation');
    $this->assertRaw('<label for="edit-textfield">Text field</label>');
    $this->assertRaw('<option value="1">One</option>');
    $this->assertRaw('<option value="4">Four</option>');
    $this->assertRaw('Site name: Test Website');

    // Check that French config elements returns the default languages elements.
    // Please note: This behavior might change.
    $translation_element = $translation_manager->getElements($webform, 'fr', TRUE);
    $this->assertEqual($elements, $translation_element);

    // Translate [site:name] into French.
    $this->drupalPostForm('/admin/config/system/site-information/translate/fr/add', ['translation[config_names][system.site][name]' => 'Site Web de test'], 'Save translation');

    // Check default elements.
    $this->drupalGet('/admin/structure/webform/manage/test_translation/translate/fr/add');

    // Check custom HTML Editor.
    $this->assertCssSelect('textarea.js-html-editor[name="translation[config_names][webform.webform.test_translation][description][value]"]');

    // Check email body's HTML Editor.
    $this->assertCssSelect('textarea.js-html-editor[name="translation[config_names][webform.webform.test_translation][handlers][email_confirmation][settings][body][value]"]');

    // Check email body's Twig Editor.
    $handler = $webform->getHandler('email_confirmation');
    $configuration = $handler->getConfiguration();
    $configuration['settings']['twig'] = TRUE;
    $handler->setConfiguration($configuration);
    $webform->save();
    $this->drupalGet('/admin/structure/webform/manage/test_translation/translate/fr/add');
    $this->assertCssSelect('textarea.js-webform-codemirror.twig[name="translation[config_names][webform.webform.test_translation][handlers][email_confirmation][settings][body]"]');

    // Check customized maxlengths.
    $this->assertCssSelect('input[name$="[title]"]');
    $this->assertNoCssSelect('input[name$="[title][maxlength"]');
    $this->assertCssSelect('input[name$="[submission_label]"]');
    $this->assertNoCssSelect('input[name$="[submission_label]"][maxlength]');

    // Create French translation.
    $edit = [
      'translation[config_names][webform.webform.test_translation][elements][textfield][title]' => 'French',
    ];
    $this->drupalPostForm('/admin/structure/webform/manage/test_translation/translate/fr/add', $edit, 'Save translation');

    // Check French translation.
    $this->drupalGet('/fr/webform/test_translation');
    $this->assertRaw('<label for="edit-textfield">French</label>');
    $this->assertRaw('Site name: Site Web de test');

    // Check translations.
    $this->drupalGet('/admin/structure/webform/manage/test_translation/translate');
    $this->assertRaw('<a href="' . base_path() . 'webform/test_translation"><strong>English (original)</strong></a>');
    $this->assertRaw('<a href="' . base_path() . 'es/webform/test_translation" hreflang="es">Spanish</a>');
    $this->assertRaw('<a href="' . base_path() . 'fr/webform/test_translation" hreflang="fr">French</a>');

    // Check French config elements only contains translated properties and
    // custom properties are removed.
    $translation_element = $translation_manager->getElements($webform, 'fr', TRUE);
    $this->assertEqual(['textfield' => ['#title' => 'French']], $translation_element);

    /**************************************************************************/
    // Submissions.
    /**************************************************************************/

    // Check English table headers are not translated.
    $this->drupalGet('/admin/structure/webform/manage/test_translation/results/submissions');
    $this->assertRaw('>Text field<');
    $this->assertRaw('>Select (options)<');
    $this->assertRaw('>Select (custom)<');
    $this->assertRaw('>Composite<');

    // Check Spanish table headers are translated.
    $this->drupalGet('/es/admin/structure/webform/manage/test_translation/results/submissions');
    $this->assertRaw('>Campo de texto<');
    $this->assertRaw('>Seleccione (opciones)<');
    $this->assertRaw('>Seleccione (personalizado)<');
    $this->assertRaw('>Compuesto<');

    // Create translated submissions.
    $this->drupalPostForm('/webform/test_translation', ['textfield' => 'English Submission'], 'Send message');
    $this->drupalPostForm('/es/webform/test_translation', ['textfield' => 'Spanish Submission'], 'Enviar mensaje');
    $this->drupalPostForm('/fr/webform/test_translation', ['textfield' => 'French Submission'], 'Send message');

    // Check computed token is NOT translated for each language because only
    // one language can be loaded for a config translation.
    $this->drupalGet('/admin/structure/webform/manage/test_translation/results/submissions');
    $this->assertRaw('Site name: Test Website');
    $this->assertNoRaw('Site name: Sitio web de prueba');
    $this->assertNoRaw('Site name: Sitio web de prueba');

    /**************************************************************************/
    // Site wide language.
    /**************************************************************************/

    // Make sure the site language is English (en).
    \Drupal::configFactory()->getEditable('system.site')->set('default_langcode', 'en')->save();

    $language_manager = \Drupal::languageManager();

    $this->drupalGet('/webform/test_translation', ['language' => $language_manager->getLanguage('en')]);
    $this->assertRaw('<label for="edit-textfield">Text field</label>');

    // Check Spanish translation.
    $this->drupalGet('/webform/test_translation', ['language' => $language_manager->getLanguage('es')]);
    $this->assertRaw('<label for="edit-textfield">Campo de texto</label>');

    // Check French translation.
    $this->drupalGet('/webform/test_translation', ['language' => $language_manager->getLanguage('fr')]);
    $this->assertRaw('<label for="edit-textfield">French</label>');

    // Change site language to French (fr).
    \Drupal::configFactory()->getEditable('system.site')->set('default_langcode', 'fr')->save();

    // Check English translation.
    $this->drupalGet('/webform/test_translation', ['language' => $language_manager->getLanguage('en')]);
    $this->assertRaw('<label for="edit-textfield">Text field</label>');

    // Check Spanish translation.
    $this->drupalGet('/webform/test_translation', ['language' => $language_manager->getLanguage('es')]);
    $this->assertRaw('<label for="edit-textfield">Campo de texto</label>');

    // Check French translation.
    $this->drupalGet('/webform/test_translation', ['language' => $language_manager->getLanguage('fr')]);
    $this->assertRaw('<label for="edit-textfield">French</label>');

    /**************************************************************************/

    // Make sure the site language is English (en).
    \Drupal::configFactory()->getEditable('system.site')->set('default_langcode', 'en')->save();

    // Duplicate translated webform.
    $edit = [
      'title' => 'DUPLICATE',
      'id' => 'duplicate',
    ];
    $this->drupalPostForm('/admin/structure/webform/manage/test_translation/duplicate', $edit, 'Save');

    // Check duplicate English translation.
    $this->drupalGet('/webform/duplicate', ['language' => $language_manager->getLanguage('en')]);
    $this->assertRaw('<label for="edit-textfield">Text field</label>');

    // Check duplicate Spanish translation.
    $this->drupalGet('/webform/duplicate', ['language' => $language_manager->getLanguage('es')]);
    $this->assertRaw('<label for="edit-textfield">Campo de texto</label>');

    // Check duplicate French translation.
    $this->drupalGet('/webform/duplicate', ['language' => $language_manager->getLanguage('fr')]);
    $this->assertRaw('<label for="edit-textfield">French</label>');
  }

  /**
   * Tests webform translate variants.
   */
  public function testTranslateVariants() {
    // Check English webform.
    $this->drupalGet('/webform/test_translation');
    $this->assertRaw('<label for="edit-textfield">Text field</label>');
    $this->assertRaw('<label for="edit-select-options">Select (options)</label>');

    // Check English webform with test variant.
    $this->drupalGet('/webform/test_translation', ['query' => ['variant' => 'test']]);
    $this->assertRaw('<label for="edit-textfield">Text field - Variant</label>');
    $this->assertRaw('<label for="edit-select-options">Select (options)</label>');

    // Check Spanish webform.
    $this->drupalGet('/es/webform/test_translation');
    $this->assertRaw('<label for="edit-textfield">Campo de texto</label>');
    $this->assertRaw('<label for="edit-select-options">Seleccione (opciones)</label>');

    // Check Spanish webform with test variant.
    $this->drupalGet('/es/webform/test_translation', ['query' => ['variant' => 'test']]);
    $this->assertRaw('<label for="edit-textfield">Campo de texto - Variante</label>');
    $this->assertRaw('<label for="edit-select-options">Seleccione (opciones)</label>');

    // Check French (not translated) webform.
    $this->drupalGet('/fr/webform/test_translation');
    $this->assertRaw('<label for="edit-textfield">Text field</label>');
    $this->assertRaw('<label for="edit-select-options">Select (options)</label>');

    // Check French (not translated) webform with test variant.
    $this->drupalGet('/fr/webform/test_translation', ['query' => ['variant' => 'test']]);
    $this->assertRaw('<label for="edit-textfield">Text field - Variant</label>');
    $this->assertRaw('<label for="edit-select-options">Select (options)</label>');

    // Remove variant element and variants.
    /** @var \Drupal\webform\WebformInterface $webform */
    $webform = Webform::load('test_translation');
    $variants = $webform->getVariants();
    foreach ($variants as $variant) {
      $webform->deleteWebformVariant($variant);
    }
    $webform->deleteElement('variant');
    $webform->save();

    // Check English webform.
    $this->drupalGet('/webform/test_translation');
    $this->assertRaw('<label for="edit-textfield">Text field</label>');
    $this->assertRaw('<label for="edit-select-options">Select (options)</label>');

    // Check English webform with test variant.
    $this->drupalGet('/webform/test_translation', ['query' => ['variant' => 'test']]);
    $this->assertRaw('<label for="edit-textfield">Text field</label>');
    $this->assertRaw('<label for="edit-select-options">Select (options)</label>');

    // Check Spanish webform.
    $this->drupalGet('/es/webform/test_translation');
    $this->assertRaw('<label for="edit-textfield">Campo de texto</label>');
    $this->assertRaw('<label for="edit-select-options">Seleccione (opciones)</label>');

    // Check Spanish webform with test variant.
    $this->drupalGet('/es/webform/test_translation', ['query' => ['variant' => 'test']]);
    $this->assertRaw('<label for="edit-textfield">Campo de texto</label>');
    $this->assertRaw('<label for="edit-select-options">Seleccione (opciones)</label>');

    // Check French (not translated) webform.
    $this->drupalGet('/fr/webform/test_translation');
    $this->assertRaw('<label for="edit-textfield">Text field</label>');
    $this->assertRaw('<label for="edit-select-options">Select (options)</label>');

    // Check French (not translated) webform with test variant.
    $this->drupalGet('/fr/webform/test_translation', ['query' => ['variant' => 'test']]);
    $this->assertRaw('<label for="edit-textfield">Text field</label>');
    $this->assertRaw('<label for="edit-select-options">Select (options)</label>');
  }

}
