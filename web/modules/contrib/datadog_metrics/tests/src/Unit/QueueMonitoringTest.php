<?php

namespace Drupal\Tests\datadog_metrics\Unit;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Queue\DatabaseQueue;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManager;
use Drupal\datadog_metrics\QueueMonitoring;
use Drupal\datadog_metrics\Utils\MetricTypesInterface;
use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;

/**
 * Tests the queue monitoring.
 *
 * @coversDefaultClass \Drupal\datadog_metrics\QueueMonitoring
 *
 * @group DatadogMetrics
 */
class QueueMonitoringTest extends TestCase {

  public $httpClient;
  public $configFactory;
  public $queueFactory;
  public $workerManager;
  public $queueMonitoring;

  /**
   * @var array|array[]
   */
  public array $queues;

  /**
   * Unit test setup function.
   */
  protected function setUp(): void {
    parent::setUp();

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

    $this->queueFactory = $this->createMock(QueueFactory::class);
    $this->workerManager = $this->createMock(QueueWorkerManager::class);
    $this->queueMonitoring = new QueueMonitoring($this->workerManager, $this->queueFactory, $this->httpClient, $this->configFactory);
    $this->queues = [
      'my_test_queue' => [
        'id' => 'my_test_queue',
      ],
    ];

  }

  /**
   * Tests the getMonitoringInformation function.
   */
  public function testGetMonitoringInformation() {
    $queueInstance = $this->createMock(DatabaseQueue::class);
    $queueInstance
      ->expects($this->once())
      ->method('numberOfItems')
      ->willReturn(5);

    $this->queueFactory
      ->expects($this->once())
      ->method('get')
      ->with('my_test_queue')
      ->willReturn($queueInstance);

    $metricTags = [
      "host:" . 'local',
      "queue:my_test_queue",
    ];

    $expectedValue = [
      [
        'metric_name' => 'queues.items',
        'metric_type' => MetricTypesInterface::COUNT,
        'metric_value' => 5,
        'metric_tags' => $metricTags,
      ],
    ];

    $actualValue = $this->queueMonitoring->getMonitoringInformation($this->queues);

    $this->assertEquals($expectedValue, $actualValue);

  }

  /**
   * Tests the getQueues function.
   */
  public function testGetQueues() {
    $this->workerManager
      ->method('getDefinitions')
      ->willReturn($this->queues);

    $actualValue = $this->queueMonitoring->getQueues();

    $this->assertEquals($this->queues, $actualValue);

  }

}
