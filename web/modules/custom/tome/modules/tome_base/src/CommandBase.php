<?php

namespace Drupal\tome_base;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Contains a base class for Tome commands.
 *
 * @internal
 */
class CommandBase extends Command {

  use ProcessTrait;
  use ExecutableFinderTrait;

  /**
   * The IO decorator.
   *
   * @var \Symfony\Component\Console\Style\SymfonyStyle
   */
  protected $io;

  /**
   * The current executable path.
   *
   * @var string
   */
  protected $executable;

  /**
   * {@inheritdoc}
   */
  protected function initialize(InputInterface $input, OutputInterface $output) {
    $this->io = new SymfonyStyle($input, $output);
    $this->executable = $this->findExecutable($input);
  }

  /**
   * {@inheritdoc}
   */
  protected function io() {
    return $this->io;
  }

}
