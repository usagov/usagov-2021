<?php

namespace Drupal\stepbystep_test\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\stepbystep_test\Form\TestForm1;
use Drupal\stepbystep_test\Form\TestForm2;

/**
 * Controller that shows 2 forms on the same page.
 */
class MultiFormController extends ControllerBase {

  /**
   * Builds the page with 2 forms.
   */
  public function build() {
    $content['form1'] = $this->formBuilder()->getForm(TestForm1::class);
    $content['form2'] = $this->formBuilder()->getForm(TestForm2::class);
    return $content;
  }

}
