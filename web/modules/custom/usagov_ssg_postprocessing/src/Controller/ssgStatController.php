<?php

namespace Drupal\usagov_ssg_postprocessing\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * An usagov_ssg_postprocessing controller for downloading files form S3.
 */
class ssgStatController extends ControllerBase {

    /**
     * Forces a download of a file from S3.
     */
    public function content() {

        $date = \Drupal::state()->get('ssg_stat_date');
        $msg = \Drupal::state()->get('ssg_stat_msg');

        if ( empty($msg) ) {
            $markup = "Static Site Generator has not been run on this environment yet.";
        } else {
            date_default_timezone_set('EST');
            $formatDate = date('D F jS Y, H:ia T', $date);
            $markup = "Static Site Generator, status as of <b>{$formatDate}:</b><br/><ul><li><i><b>{$msg}<b></i></li></ul>";
        }

        return array('#markup' => $markup, '#cache' => array('max-age' => 0));
    }
}
