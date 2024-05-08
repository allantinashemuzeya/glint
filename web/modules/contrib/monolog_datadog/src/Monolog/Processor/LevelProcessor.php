<?php

declare(strict_types=1);

namespace Drupal\monolog_datadog\Monolog\Processor;

use Monolog\Logger;
use Monolog\Processor\ProcessorInterface;

/**
 * Processor to map levels to Datadog status.
 */
class LevelProcessor implements ProcessorInterface {

  /**
   * Map Monolog\Logger logging levels to Datadog alert_type.
   */
  const ALERT_TYPE_MAP = [
    Logger::DEBUG     => 'info',
    Logger::INFO      => 'info',
    Logger::NOTICE    => 'info',
    Logger::WARNING   => 'warning',
    Logger::ERROR     => 'error',
    Logger::ALERT     => 'error',
    Logger::CRITICAL  => 'error',
    Logger::EMERGENCY => 'error',
  ];

  /**
   * Processor function.
   *
   * @param array $record
   *   The record.
   *
   * @return array
   *   The processed record.
   */
  public function __invoke(array $record): array {
    $record['level'] = self::ALERT_TYPE_MAP[$record['level']];
    return $record;
  }

}
