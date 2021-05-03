<?php

namespace Drupal\scheduler\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\scheduler\SchedulerManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class LightweightCronController.
 *
 * @package Drupal\scheduler\Controller
 */
class LightweightCronController extends ControllerBase {

  /**
   * The scheduler manager.
   *
   * @var \Drupal\scheduler\SchedulerManager
   */
  protected $schedulerManager;

  /**
   * LightweightCronController constructor.
   *
   * @param \Drupal\scheduler\SchedulerManager $scheduler_manager
   *   The scheduler manager.
   */
  public function __construct(SchedulerManager $scheduler_manager) {
    $this->schedulerManager = $scheduler_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('scheduler.manager')
    );
  }

  /**
   * Index.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The http response.
   */
  public function index() {
    $this->schedulerManager->runLightweightCron();

    return new Response('', Response::HTTP_NO_CONTENT);
  }

  /**
   * Checks access.
   *
   * @param string $cron_key
   *   The cron key.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access($cron_key) {
    $valid_cron_key = $this->config('scheduler.settings')
      ->get('lightweight_cron_access_key');
    return AccessResult::allowedIf($valid_cron_key == $cron_key);
  }

}
