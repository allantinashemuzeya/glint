<?php

declare(strict_types=1);

namespace Drupal\monolog\Logger\ConditionResolver;

/**
 * Choose which formatter to use whether we are in CLI or not.
 */
class CLI implements ConditionResolverInterface {

  /**
   * {@inheritdoc}
   */
  public function resolve(): bool {
    return (php_sapi_name() == 'cli' || (array_key_exists('argc', $_SERVER) && is_numeric($_SERVER['argc']) && $_SERVER['argc'] > 0));
  }

}