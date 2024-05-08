<?php

namespace Drupal\Tests\datadog_metrics\Unit;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\Select;
use Drupal\Core\Database\StatementWrapper;
use Drupal\Core\State\State;
use Drupal\datadog_metrics\EventSubscriber\MigrationEventSubscriber;
use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Drupal\migrate\Event\MigrateRowDeleteEvent;
use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\migrate\Row;
use Drupal\migrate_plus\Entity\Migration;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Client;

/**
 * Tests the migration event subscriber.
 *
 * @coversDefaultClass \Drupal\datadog_metrics\EventSubscriber\MigrationEventSubscriber
 *
 * @group DatadogMetrics
 */
class MigrationEventSubscriberTest extends UnitTestCase {

  public $event;
  public $state;
  public $connection;
  public $migration;
  public $migrationPluginManager;

  /**
   * Unit test setup function.
   */
  protected function setUp(): void {
    parent::setUp();
    $this->event = $this->createMock(Event::class);
    $this->state = $this->createMock(State::class);
    $this->connection = $this->createMock(Connection::class);

    $this->migration = $this->createMock(Migration::class);
    $this->migration
      ->expects($this->once())
      ->method('id')
      ->willReturn('test_migration');

    $this->config = $this->createMock(ImmutableConfig::class);
    $this->config
      ->method('get')
      ->will(
        $this->returnValueMap(
          [
            ['api_key', 'testkey'],
            ['region', 'eu'],
          ]
        )
      );

    $this->configFactory = $this->createMock(ConfigFactory::class);
    $this->configFactory
      ->method('get')
      ->with('datadog.settings')
      ->willReturn($this->config);

    $this->httpClient = $this->createMock(Client::class);

    $this->migrationPluginManager = $this->createMock(MigrationPluginManager::class);
  }

  /**
   * Tests the onPreImport function.
   */
  public function testOnPreImport() {
    $migrateImportEvent = $this->createMock(MigrateImportEvent::class);
    $migrateImportEvent
      ->expects($this->once())
      ->method('getMigration')
      ->willReturn($this->migration);

    $this->state
      ->expects($this->exactly(4))
      ->method('set')
      ->with($this->logicalOr(
        $this->equalTo('test_migration.processed_count'),
        $this->equalTo('test_migration.created_count'),
        $this->equalTo('test_migration.updated_count'),
        $this->equalTo('test_migration.deleted_count'),
      ),
        $this->equalTo(0)
      );

    $migrationEventSubscriber = new MigrationEventSubscriber(
      $this->state,
      $this->connection,
      $this->httpClient,
      $this->configFactory,
      $this->migrationPluginManager
    );

    $migrationEventSubscriber->onPreImport($migrateImportEvent);
  }

  /**
   * Tests the onPostRowSave function.
   */
  public function testOnPostRowSave() {
    $row = $this->createMock(Row::class);

    $row->method('getIdMap')->willReturn([
      'sourceid1' => 'testid',
    ]);

    $migratePostRowSaveEvent = $this->createMock(MigratePostRowSaveEvent::class);
    $migratePostRowSaveEvent
      ->expects($this->once())
      ->method('getRow')
      ->willReturn($row);

    $migratePostRowSaveEvent
      ->expects($this->once())
      ->method('getMigration')
      ->willReturn($this->migration);

    $statementWrapper = $this->createMock(StatementWrapper::class);
    $statementWrapper
      ->expects($this->once())
      ->method('fetchField')
      ->willReturn('testid');

    $select = $this->createMock(Select::class);
    $select
      ->expects($this->once())
      ->method('fields')
      ->with('m', ['sourceid1'])
      ->willReturn($select);

    $select
      ->expects($this->once())
      ->method('condition')
      ->with('m.sourceid1', 'testid')
      ->willReturn($select);

    $select
      ->expects($this->once())
      ->method('execute')
      ->willReturn($statementWrapper);

    $this->connection = $this->createMock(Connection::class);
    $this->connection
      ->expects($this->once())
      ->method('select')
      ->with('migrate_map_test_migration')
      ->willReturn($select);

    $this->state
      ->expects($this->exactly(2))
      ->method('get')
      ->with($this->logicalOr(
        $this->equalTo('test_migration.updated_count', 0),
        $this->equalTo('test_migration.processed_count', 0)
      ))
      ->will(
        $this->returnValueMap(
          [
            ['organization_import.updated_count', 0, 8],
            ['test_migration.processed_count', 0, 32],
          ]
        )
      );

    $this->state
      ->expects($this->exactly(2))
      ->method('set')
      ->with($this->logicalOr(
        $this->equalTo('test_migration.updated_count', 9),
        $this->equalTo('test_migration.processed_count', 33)
      ));

    $migrationEventSubscriber = new MigrationEventSubscriber(
      $this->state,
      $this->connection,
      $this->httpClient,
      $this->configFactory,
      $this->migrationPluginManager
    );

    $migrationEventSubscriber->onPostRowSave($migratePostRowSaveEvent);
  }

  /**
   * Tests the onPostRowDelete function.
   */
  public function testOnPostRowDelete() {
    $migrateRowDeleteEvent = $this->createMock(MigrateRowDeleteEvent::class);
    $migrateRowDeleteEvent
      ->expects($this->once())
      ->method("getMigration")
      ->willReturn($this->migration);

    $this->state
      ->expects($this->exactly(2))
      ->method('get')
      ->with($this->logicalOr(
        $this->equalTo('test_migration.deleted_count', 2),
        $this->equalTo('test_migration.processed_count', 33)
      ));

    $this->state
      ->expects($this->exactly(2))
      ->method('set')
      ->with($this->logicalOr(
        $this->equalTo('test_migration.deleted_count', 3),
        $this->equalTo('test_migration.processed_count', 34)
      ));

    $migrationEventSubscriber = new MigrationEventSubscriber(
      $this->state,
      $this->connection,
      $this->httpClient,
      $this->configFactory,
      $this->migrationPluginManager
    );

    $migrationEventSubscriber->onPostRowDelete($migrateRowDeleteEvent);

  }

  public function onPostImport(){
    $migrateImportEvent = $this->createMock(MigrateImportEvent::class);
    $migrateImportEvent
      ->expects($this->once())
      ->method('getMigration')
      ->willReturn($this->migration);

    $migrationInstance = $this->createMock(\Drupal\migrate\Plugin\Migration::class);
    $migrationInstance->method('getStatus')->willReturn(0);
    $migrationInstance->method('getPluginDefinition')->willReturn([
      'migration_group' => 'bs_state_calendar',
    ]);

    $this->migrationPluginManager
      ->expects($this->once())
      ->method('createInstance')
      ->with('test_migration')
      ->willReturn($migrationInstance);

    $this->httpClient
      ->expects($this->once())
      ->method('__call');

    $migrationEventSubscriber = new MigrationEventSubscriber(
      $this->state,
      $this->connection,
      $this->httpClient,
      $this->configFactory,
      $this->migrationPluginManager
    );

    $migrationEventSubscriber->onPostImport($migrateImportEvent);

  }

}
