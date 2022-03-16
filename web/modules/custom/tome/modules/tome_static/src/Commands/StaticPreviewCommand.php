<?php

namespace Drupal\tome_static\Commands;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\tome_base\CommandBase;
use Drupal\tome_static\StaticGeneratorInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Contains the tome:preview command.
 *
 * @internal
 */
class StaticPreviewCommand extends CommandBase {

  use StringTranslationTrait;

  /**
   * The static service.
   *
   * @var \Drupal\tome_static\StaticGeneratorInterface
   */
  protected $static;

  /**
   * Constructs a StaticPreviewCommand instance.
   *
   * @param \Drupal\tome_static\StaticGeneratorInterface $static
   *   The static service.
   */
  public function __construct(StaticGeneratorInterface $static) {
    parent::__construct();
    $this->static = $static;
  }

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this->setName('tome:preview')
      ->setDescription('Preview your static site.')
      ->addOption('port', NULL, InputOption::VALUE_OPTIONAL, 'The port to run the server on.', 8889);
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    if (!file_exists($this->static->getStaticDirectory())) {
      $this->io()->error('Static directory does not exist. Have you ran the "tome:static" command yet?');
      return 1;
    }
    $options = $input->getOptions();
    $url = '127.0.0.1:' . $options['port'];
    $this->startBrowser('http://' . $url . base_path(), 2);
    $this->runCommand('php -S ' . escapeshellarg($url), $this->static->getStaticDirectory(), NULL);
  }

  /**
   * Opens a web browser for the given URL.
   *
   * @param string $url
   *   An absolute URL.
   * @param int $sleep
   *   An amount of time to wait before opening the browser, in seconds.
   */
  protected function startBrowser($url, $sleep = NULL) {
    $browser = FALSE;
    foreach (['xdg-open', 'open'] as $command) {
      if (shell_exec("which $command 2> /dev/null")) {
        $browser = $command;
        break;
      }
    }
    if (!$browser) {
      $this->io->success("Static site server running at $url");
      return;
    }
    $this->io->success("Opening browser at $url");
    if ($sleep) {
      sleep($sleep);
    }
    $process = new Process([$browser, $url]);
    $process->run();
  }

}
