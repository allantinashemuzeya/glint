<?php

namespace Drupal\datadog_metrics;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\Connection;
use Drupal\Core\State\StateInterface;
use Drupal\datadog_metrics\Utils\MetricTypesInterface;
use Drupal\datadog_metrics\Utils\MonitoringUtils;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use GuzzleHttp\ClientInterface;

/**
 * Get and submit migrations monitoring information as metrics to datadog.
 */
class MigrationsMonitoring {

  use MonitoringUtils;

  /**
   * @var \GuzzleHttp\ClientInterface
   */
  public ClientInterface $httpClient;

  /**
   * @var \Drupal\migrate\Plugin\MigrationPluginManagerInterface
   */
  public MigrationPluginManagerInterface $migrationPluginManager;

  /**
   * @var \Drupal\Core\State\StateInterface
   */
  public StateInterface $state;

  /**
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  public ImmutableConfig $datadogConfig;

  /**
   * @var \Drupal\Core\Database\Connection
   */
  private Connection $connection;

  /**
   * MigrationsMonitoring constructor.
   *
   * @param \GuzzleHttp\ClientInterface $httpClient
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   * @param \Drupal\migrate\Plugin\MigrationPluginManagerInterface $migrationPluginManager
   * @param \Drupal\Core\State\StateInterface $state
   * @param \Drupal\Core\Database\Connection $connection
   */
  public function __construct(
    ClientInterface $httpClient,
    ConfigFactoryInterface $configFactory,
    MigrationPluginManagerInterface $migrationPluginManager,
    StateInterface $state,
    Connection $connection
  ) {
    $this->httpClient = $httpClient;
    $this->datadogConfig = $configFactory->get('datadog.settings');
    $this->migrationPluginManager = $migrationPluginManager;
    $this->state = $state;
    $this->connection = $connection;
  }

  /**
   * Gets the monitoring information and submits it to datadog.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function migrationsMonitoring() {
    $migrations = $this->migrationPluginManager->getDefinitions();
    $monitoringInformation = $this->getMonitoringInformation($migrations);
    $this->submitToDatadog($monitoringInformation, $this->datadogConfig, $this->httpClient);
    $this->resetMigrationsMonitoringStates($migrations);
  }

  /**
   * Gets the monitoring information.
   *
   * @param array $migrations
   *
   * @return array
   */
  public function getMonitoringInformation(array $migrations): array {
    $monitoringInformation = [];

    foreach ($migrations as $migration) {
      $migrationId = $migration['id'];
      $migrationInstance = $this->migrationPluginManager->createInstance($migrationId);
      $migrationStatus = $migrationInstance->getStatus();
      $migrationGroup = $migrationInstance->getPluginDefinition()["migration_group"];
      $tableName = "migrate_map_$migrationId";
      $migrateMapTableExists =
        $this->connection->schema()->tableExists($tableName);

      if ($migrateMapTableExists){
        $destinationCount = $this->connection
          ->select($tableName)
          ->countQuery()
          ->execute()
          ->fetchField();
      } else {
        $destinationCount = "0";
      }

      $metricTags = [
        "host:" . $this->datadogConfig->get('env'),
        "migration:" . $migrationId,
        "migration_group:" . $migrationGroup,
      ];

      $monitoringInformation[] = $this->getMonitoringInformationEntry(
        'migration.status',
        MetricTypesInterface::GAUGE,
        $migrationStatus,
        $metricTags
      );

      $monitoringInformation[] = $this->getMonitoringInformationEntry(
        'migration.items',
        MetricTypesInterface::COUNT,
        (int) $destinationCount,
        $metricTags
      );

      foreach (['processed', 'created', 'updated', 'deleted'] as $status) {
        $monitoringInformation[] = $this->getMonitoringInformationEntry(
          "migration.$status",
          MetricTypesInterface::COUNT,
          $this->state->get($migrationId . '.' . $status . '_count', 0),
          $metricTags
        );
      }

    }

    return $monitoringInformation;
  }

  /**
   * Resets the monitoring status.
   *
   * @param array $migrations
   */
  public function resetMigrationsMonitoringStates(array $migrations): void {
    foreach ($migrations as $migration) {
      $migrationId = $migration['id'];

      foreach (['processed', 'created', 'updated', 'deleted'] as $status) {
        $stateKey = $migrationId . '.' . $status . '_count';
        $this->state->set($stateKey, 0);
      }

    }

  }

}
