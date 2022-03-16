<?php

namespace Drupal\tome_base;

use Symfony\Component\Process\Process;

/**
 * Shared methods for running processes.
 *
 * @internal
 */
trait ProcessTrait {

  /**
   * Runs commands with concurrency.
   *
   * @param string[] $commands
   *   An array of commands to execute.
   * @param int $concurrency
   *   The number of concurrent processes to execute.
   * @param int $retry_count
   *   The number of times to retry a failed command.
   * @param callback|\Closure $callback
   *   (optional) A callback to invoke for each completed callback.
   *
   * @return array
   *   An array of errors encountered when running commands.
   */
  protected function runCommands(array $commands, $concurrency, $retry_count, $callback = NULL) {
    $current_processes = [];
    $collected_errors = [];

    $retry_callback = function (&$current_process) use (&$collected_errors, $retry_count) {
      /** @var \Symfony\Component\Process\Process $process */
      $process = $current_process['process'];
      $command = $process->getCommandLine();
      if (!$process->isRunning() && !$process->isSuccessful() && $current_process['retry'] < $retry_count) {
        $collected_errors[] = "Retrying \"{$command}\" after failure...";
        $current_process['process'] = $process->restart();
        ++$current_process['retry'];
      }
    };

    $filter_callback = function ($current_process) use (&$collected_errors, $callback) {
      /** @var \Symfony\Component\Process\Process $process */
      $process = $current_process['process'];
      $is_running = $process->isRunning();
      $command = $process->getCommandLine();
      if (!$is_running) {
        if (!$process->isSuccessful()) {
          $error_output = $process->getErrorOutput();
          $collected_errors[] = "Error when running \"{$command}\":\n  $error_output";
        }
        if ($callback) {
          call_user_func($callback, $current_process['process']);
        }
      }
      return $is_running;
    };

    while ($commands || $current_processes) {
      array_walk($current_processes, $retry_callback);
      $current_processes = array_filter($current_processes, $filter_callback);
      if ($commands && count($current_processes) < $concurrency) {
        $command = array_shift($commands);
        $process = new Process($command, isset($_SERVER['PWD']) ? $_SERVER['PWD'] : NULL);
        $process->start();
        $current_processes[] = [
          'process' => $process,
          'retry' => 0,
        ];
      }
      usleep(50000);
    }

    return $collected_errors;
  }

  /**
   * Runs a single command and outputs errors if encountered.
   *
   * @param string|array $command
   *   The command to run.
   * @param string $cwd
   *   (Optional) The working directory to use.
   * @param int|float|null $timeout
   *   The timeout in seconds or null to disable.
   *
   * @return bool
   *   Whether or not the process executed successfully.
   */
  protected function runCommand($command, $cwd = NULL, $timeout = 60) {
    $process = new Process($command, $cwd, NULL, NULL, $timeout);
    $process->run();
    $successful = $process->isSuccessful();
    $errors = [];
    if (!$successful) {
      $errors[] = "Error when running \"{$command}\"";
      if ($error_output = $process->getErrorOutput()) {
        $errors[] = $error_output;
      }
      $this->displayErrors($errors);
    }
    return $successful;
  }

  /**
   * Displays errors using the IO component.
   *
   * @param string[] $collected_errors
   *   An array of error messages to display.
   */
  protected function displayErrors(array $collected_errors) {
    foreach ($collected_errors as $error) {
      $this->io()->error($error);
    }
  }

  /**
   * Returns the IO decorator, for reporting errors.
   *
   * @return \Symfony\Component\Console\Style\SymfonyStyle
   *   The IO decorator.
   */
  abstract public function io();

}
