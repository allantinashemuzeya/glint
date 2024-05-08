<?php

namespace Drupal\datadog_metrics\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\State\StateInterface;
use Drupal\datadog_metrics\Utils\MetricTypesInterface;
use Drupal\datadog_metrics\Utils\MonitoringUtils;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Drupal\migrate\Event\MigrateRowDeleteEvent;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to some migration events and set monitoring information.
 */
class MigrationEventSubscriber implements EventSubscriberInterface {
  use MonitoringUtils;

  /**
   * @var \Drupal\Core\State\StateInterface
   */
  private StateInterface $state;

  /**
   * @var \Drupal\Core\Database\Connection
   */
  private Connection $connection;

  /**
   * @var ClientInterface
   */
  private ClientInterface $httpClient;

  /**
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private $datadogConfig;

  /**
   * @var MigrationPluginManagerInterface
   */
  private $migrationPluginManager;


  /**
   * MigrationEventSubscriber constructor.
   *
   * @param \Drupal\Core\State\StateInterface $state
   * @param \Drupal\Core\Database\Connection $connection
   * @param \GuzzleHttp\ClientInterface $httpClient
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   * @param \Drupal\migrate\Plugin\MigrationPluginManagerInterface $migrationPluginManager
   */
  public function __construct(
    StateInterface $state,
    Connection $connection,
    ClientInterface $httpClient,
    ConfigFactoryInterface $configFactory,
    MigrationPluginManagerInterface $migrationPluginManager
  ) {
    $this->connection = $connection;
    $this->state = $state;
    $this->httpClient = $httpClient;
    $this->datadogConfig = $configFactory->get('datadog.settings');
    $this->migrationPluginManager = $migrationPluginManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[MigrateEvents::PRE_IMPORT][] = ['onPreImport'];
    $events[MigrateEvents::POST_ROW_SAVE][] = ['onPostRowSave'];
    $events[MigrateEvents::POST_ROW_DELETE][] = ['onPostRowDelete'];
    $events[MigrateEvents::POST_IMPORT][] = ['onPostImport'];

    return $events;
  }

  /**
   * Function executed at the beginning of a migration import operation
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   */
  public function onPreImport(MigrateImportEvent $event) {
    $migration = $event->getMigration();
    $migrationId = $migration->id();

    foreach (['processed', 'created', 'updated', 'deleted'] as $status) {
      $stateKey = $migrationId . '.' . $status . '_count';
      $this->state->set($stateKey, 0);
    }
  }

  /**
   * Function executed just after a single item has been imported
   *
   * @param \Drupal\migrate\Event\MigratePostRowSaveEvent $event
   */
  public function onPostRowSave(MigratePostRowSaveEvent $event): void {

    $row = $event->getRow();
    $migration = $event->getMigration();
    $migrationId = $migration->id();

    if (isset($row->getIdMap()["sourceid1"])) {
      $sourceId = $row->getIdMap()["sourceid1"];
    }
    elseif ($row->get('ids')) {
      $idKey = array_key_first($row->get('ids'));
      $sourceId = $row->get($idKey);
    }
    else {
      $sourceId = array_values($row->getSourceIdValues())[0];
    }

    $tableName = "migrate_map_$migrationId";

    $query = $this->connection->select($tableName, 'm')
      ->fields('m', ['sourceid1'])
      ->condition('m.sourceid1', $sourceId);

    $exists = $query->execute()->fetchField();

    if (!$exists) {
      // If there is no entry in the migrate_map table,
      // initialize status to 'created'.
      $status = 'created';
    }
    else {
      // If there is already an entry in the migrate_map table,
      // initialize status to 'updated'.
      $status = 'updated';
    }
    $this->incrementCount($migrationId, $status);
    $this->incrementCount($migrationId, 'processed');
  }

  /**
   * Function executed when about to delete a single item.
   *
   * @param \Drupal\migrate\Event\MigrateRowDeleteEvent $event
   */
  public function onPostRowDelete(MigrateRowDeleteEvent $event): void {
    $migration = $event->getMigration();
    $migrationId = $migration->id();

    // Initialize status to 'deleted'.
    $status = 'deleted';
    $this->incrementCount($migrationId, $status);

    // Increment the 'processed' count.
    $this->incrementCount($migrationId, 'processed');
  }

  /**
   * Function executed when finishing a migration import operation.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function onPostImport(MigrateImportEvent $event){
    $migration = $event->getMigration();
    $migrationId = $migration->id();
    $migrationInstance = $this->migrationPluginManager->createInstance($migrationId);
    $migrationGroup = $migrationInstance->getPluginDefinition()["migration_group"];

    $metricTags = [
      "host:" . $this->datadogConfig->get('env'),
      "migration:" . $migrationId,
      "migration_group:" . $migrationGroup,
    ];

    $monitoringInformation = [
      $this->getMonitoringInformationEntry(
        'migration.import',
        MetricTypesInterface::RATE,
        1,
        $metricTags
      )
    ];

    $this->submitToDatadog($monitoringInformation, $this->datadogConfig, $this->httpClient);

  }

  /**
   * Increments the items count.
   *
   * @param $migrationId
   * @param $status
   */
  private function incrementCount($migrationId, $status): void {
    $countKey = $migrationId . '.' . $status . '_count';
    $count = $this->state->get($countKey, 0);
    $count++;
    $this->state->set($countKey, $count);
  }

}
