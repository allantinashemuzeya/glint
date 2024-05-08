<?php

declare(strict_types = 1);

namespace Drupal\monolog_datadog\Commands;

use Drush\Commands\DrushCommands;

/**
 * A command to test if the logging service is working.
 */
class TestLoggingService extends DrushCommands {

  /**
   * Sends logging messages to test the logging services.
   *
   * @command monolog_datadog:test-logging-services
   */
  public function testLoggingServices(): void {
    \Drupal::logger('drupal_test')->error('Test error');
    \Drupal::logger('drupal_test')->warning('Test warning');
    \Drupal::logger('drupal_test')->debug('Test debug');
    \Drupal::logger('drupal_test')->info('Test info');

    $this->io()->success('Test logging messages sent.');
  }

}
