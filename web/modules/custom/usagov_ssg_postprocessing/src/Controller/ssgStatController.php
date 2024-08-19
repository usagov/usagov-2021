<?php

namespace Drupal\usagov_ssg_postprocessing\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * An usagov_ssg_postprocessing controller for stating the current static-site-gen status.
 */
class ssgStatController extends ControllerBase {

    public function content() {

        $date = \Drupal::state()->get('ssg_stat_date');
        $msg = \Drupal::state()->get('ssg_stat_msg');

        if ( empty($msg) ) {
            $markup = "Static Site Generator has not been run on this environment yet.";
        } else {
            date_default_timezone_set('America/New_York');
            $formatDate = date('D F jS Y, h:ia', $date);
            $markup = "Static Site Generator, status as of <b>{$formatDate} EST:</b><br/><ul><li><i><b>{$msg}<b></i></li></ul>";
        }

        return array('#markup' => $markup, '#cache' => array('max-age' => 0));
    }
}
