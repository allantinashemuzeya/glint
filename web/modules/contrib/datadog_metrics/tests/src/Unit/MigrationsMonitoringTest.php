<?php

namespace Drupal\Tests\datadog_metrics\Unit;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\Select;
use Drupal\Core\Database\StatementWrapper;
use Drupal\Core\State\State;
use Drupal\datadog_metrics\MigrationsMonitoring;
use Drupal\datadog_metrics\Utils\MetricTypesInterface;
use Drupal\migrate\Plugin\Migration;
use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\mysql\Driver\Database\mysql\Schema;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Client;

/**
 * Tests the migration monitoring.
 *
 * @coversDefaultClass \Drupal\datadog_metrics\MigrationsMonitoring
 *
 * @group DatadogMetrics
 */
class MigrationsMonitoringTest extends UnitTestCase {

  public $httpClient;
  public $configFactory;
  public $migrationPluginManager;
  public $state;

  /**
   * @var \Drupal\datadog_metrics\MigrationsMonitoring
   */
  public MigrationsMonitoring $migrationsMonitoring;

  /**
   * @var array
   */
  public array $migrations;

  /**
   * @var string
   */
  public string $migrationId;

  /**
   * Unit test setup function.
   */
  protected function setUp(): void {
    parent::setUp();

    $this->migrations = [
      'organization_import' => [
        'id' => 'organization_import',
      ],
    ];

    $this->migrationId = array_values($this->migrations)[0]['id'];
    $this->httpClient = $this->createMock(Client::class);
    $this->configFactory = $this->createMock(ConfigFactory::class);
    $config = $this->getMockBuilder(ImmutableConfig::class)
      ->disableOriginalConstructor()
      ->getMock();

    $config->method('get')
      ->with('env')
      ->willReturn('local');

    $this->configFactory
      ->method('get')
      ->with('datadog.settings')
      ->willReturn($config);

    $this->migrationPluginManager = $this->createMock(MigrationPluginManager::class);
    $this->state = $this->createMock(State::class);
    $this->connection = $this->createMock(Connection::class);
    $this->migrationsMonitoring = new MigrationsMonitoring($this->httpClient, $this->configFactory, $this->migrationPluginManager, $this->state, $this->connection);

  }

  /**
   * Tests the getMonitoringInformation function.
   */
  public function testGetMonitoringInformation() {
    $migrationInstance = $this->createMock(Migration::class);
    $migrationInstance->method('getStatus')->willReturn(0);
    $migrationInstance->method('getPluginDefinition')->willReturn([
      'migration_group' => 'bs_state_calendar',
    ]);

    $this->migrationPluginManager
      ->method('createInstance')
      ->willReturn($migrationInstance);

    $this->state
      ->method('get')
      ->will(
        $this->returnValueMap(
          [
            [$this->migrationId . '.processed_count', 0, 52],
            [$this->migrationId . '.created_count', 0, 8],
            [$this->migrationId . '.updated_count', 0, 41],
            [$this->migrationId . '.deleted_count', 0, 3],
          ]
        )
      );

    $statementWrapper = $this->createMock(StatementWrapper::class);
    $statementWrapper
      ->expects($this->once())
      ->method('fetchField')
      ->willReturn('2113');

    $select = $this->createMock(Select::class);
    $select
      ->expects($this->once())
      ->method('countQuery')
      ->willReturn($select);

    $select
      ->expects($this->once())
      ->method('execute')
      ->willReturn($statementWrapper);

    $schema = $this->createMock(Schema::class);
    $schema
      ->expects($this->once())
      ->method('tableExists')
      ->with("migrate_map_". $this->migrationId)
      ->willReturn(true);

    $this->connection
      ->expects($this->once())
      ->method('select')
      ->willReturn($select);

    $this->connection
      ->expects($this->once())
      ->method('schema')
      ->willReturn($schema);

    $actualMonitoringInformation = $this->migrationsMonitoring->getMonitoringInformation($this->migrations);

    $metricTags = [
      "host:" . 'local',
      "migration:" . $this->migrationId,
      "migration_group:" . 'bs_state_calendar',
    ];

    $expectedMonitoringInformation = [
      [
        'metric_name' => 'migration.status',
        'metric_type' => MetricTypesInterface::GAUGE,
        'metric_value' => 0,
        'metric_tags' => $metricTags,
      ],
      [
        'metric_name' => 'migration.items',
        'metric_type' => MetricTypesInterface::COUNT,
        'metric_value' => 2113,
        'metric_tags' => $metricTags,
      ],
      [
        'metric_name' => 'migration.processed',
        'metric_type' => MetricTypesInterface::COUNT,
        'metric_value' => 52,
        'metric_tags' => $metricTags,
      ],
      [
        'metric_name' => 'migration.created',
        'metric_type' => MetricTypesInterface::COUNT,
        'metric_value' => 8,
        'metric_tags' => $metricTags,
      ],
      [
        'metric_name' => 'migration.updated',
        'metric_type' => MetricTypesInterface::COUNT,
        'metric_value' => 41,
        'metric_tags' => $metricTags,
      ],
      [
        'metric_name' => 'migration.deleted',
        'metric_type' => MetricTypesInterface::COUNT,
        'metric_value' => 3,
        'metric_tags' => $metricTags,
      ],

    ];

    $this->assertEquals($expectedMonitoringInformation, $actualMonitoringInformation);

  }

  /**
   * Tests the resetMigrationsMonitoringStates function.
   */
  public function testResetMigrationsMonitoringStates() {
    $this->state
      ->expects($this->exactly(4))
      ->method('set')
      ->with($this->logicalOr(
        $this->equalTo($this->migrationId . '.processed_count'),
        $this->equalTo($this->migrationId . '.created_count'),
        $this->equalTo($this->migrationId . '.updated_count'),
        $this->equalTo($this->migrationId . '.deleted_count'),
      ),
        $this->equalTo(0)
      );

    $this->migrationsMonitoring->resetMigrationsMonitoringStates($this->migrations);

  }

}
