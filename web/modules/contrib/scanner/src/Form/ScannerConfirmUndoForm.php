<?php

namespace Drupal\scanner\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Component\Plugin\Exception\PluginException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Messenger\MessengerTrait;

/**
 * Displayed to confirm that the user want to undo the replace operation.
 */
class ScannerConfirmUndoForm extends ConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'scanner_confirm_undo_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = null) {
    $form = parent::buildForm($form, $form_state);
    $form['undo_id'] = [
      '#type' => 'hidden',
      '#value' => $id
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $pluginManager = \Drupal::service('plugin.manager.scanner');
    $connection = \Drupal::service('database');
    $undo_id = $form_state->getValue('undo_id',0);
    if (!empty($undo_id) && $undo_id > 0) {
      // Query the database in order to find the specific record we're trying
      // to undo.
      $query = $connection->query('SELECT undo_data from scanner WHERE undone = :undone and undo_id = :id',[':undone' => 0, ':id' => $undo_id]);
      $results = $query->fetchCol()[0];
      $data = unserialize($results);

      $operations[] = ['\Drupal\scanner\Form\ScannerConfirmUndoForm::batchUndo', [$data, $undo_id]];

      $batch = [
        'title' => t('Scanner Replace Batch'),
        'operations' => $operations,
        'finished' => '\Drupal\scanner\Form\ScannerConfirmUndoForm::batchFinished',
        'progress_message' => t('Processed @current out of @total'),
      ];
      batch_set($batch);
    }
    $form_state->setRedirect('scanner.undo');
  }

  /**
   * Helper function to handle batch operation.
   * 
   * @param array $data
   *  The array containing the revision ids for each entity.
   * @param string $undo_id
   *  The id of the undo record.
   * @param array $context
   *  The array containing the data which is persisted across jobs.
   */
  public static function batchUndo($data, $undo_id,&$context) {
    $pluginManager = \Drupal::service('plugin.manager.scanner');

    try {
      $plugin = $pluginManager->createInstance('scanner_entity');
      // This process can take a while so we want to extend the execution time
      // if it's less then 300 (5 minutes).
      if (ini_get('max_execution_time') < 300) {
        ini_set('max_execution_time','300');
      }
    }
    catch (PluginException $e) {
      // The instance could not be found so fail gracefully and let the user
      // know.
      \Drupal::logger('scanner')->error($e->getMessage());
      \Drupal::messenger()->addError(t('An error occured: '. $e->getMessage()));
    }
    $plugin->undo($data);
    $context['results']['undo_id'] = $undo_id;
    $context['message'] = 'Undoing...';
  }

  public static function batchFinished($success, $results, $operations) {
    if ($success) {
      $connection = \Drupal::service('database');
      // Set the status of the record to '1', denoting being done.
      $updateQuery = $connection->update('scanner')
        ->fields(['undone' => 1])
        ->condition('undo_id', $results['undo_id'],'=')
        ->execute();
    }
    else {
      $message = t('There were some errors.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('scanner.undo');
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return t('Are you sure you want to undo this?');
  }

}
