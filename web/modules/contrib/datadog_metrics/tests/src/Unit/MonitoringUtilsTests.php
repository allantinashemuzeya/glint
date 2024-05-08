<?php

namespace Drupal\Tests\datadog_metrics\Unit;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\datadog_metrics\Utils\MetricTypesInterface;
use Drupal\datadog_metrics\Utils\MonitoringUtils;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Client;

/**
 * Tests the monitoring utils.
 *
 * @coversDefaultClass \Drupal\datadog_metrics\Utils\MonitoringUtils
 *
 * @group DatadogMetrics
 */
class MonitoringUtilsTests extends UnitTestCase {

  public $monitoringUtilsMock;

  /**
   * @var int
   */
  public int $timeStamp;

  /**
   * Unit test setup function.
   */
  protected function setUp(): void {
    parent::setUp();
    $this->timeStamp = time();
    $this->monitoringUtilsMock = new class($this->timeStamp) {
      use MonitoringUtils;

      public $timeStamp;

      public function __construct($timeStamp) {
        $this->timeStamp = $timeStamp;
      }

      public function getTime(): int {
        return $this->timeStamp;
      }

    };

  }

  /**
   * Test the getDatadogSeriesEntry function.
   */
  public function testGetDatadogSeriesEntry() {
    $metricName = "testName";
    $metricType = MetricTypesInterface::COUNT;
    $metricValue = 12;
    $tags = [
      "foo:bar",
      "test:test:",
    ];

    $expectedSeriesEntry = [
      "metric" => $metricName,
      "type" => $metricType,
      "points" => [
        [
          "timestamp" => $this->timeStamp,
          "value" => $metricValue,
        ],
      ],
      "tags" => $tags,
    ];

    $actualSeriesEntry = $this->monitoringUtilsMock->getDatadogSeriesEntry($metricName, $metricType, $metricValue, $tags);

    $this->assertEquals(
      $expectedSeriesEntry,
      $actualSeriesEntry
    );

  }

  /**
   * Tests the getMonitoringInformationEntry function.
   */
  public function testGetMonitoringInformationEntry() {
    $name = "testName";
    $type = MetricTypesInterface::COUNT;
    $value = 12;
    $tags = [
      "foo:bar",
      "test:test:",
    ];

    $expectedInformationEntry = [
      'metric_name' => $name,
      'metric_type' => $type,
      'metric_value' => $value,
      'metric_tags' => $tags,
    ];

    $this->assertEquals(
      $expectedInformationEntry,
      $this->monitoringUtilsMock->getMonitoringInformationEntry($name, $type, $value, $tags)
    );

  }

  /**
   * Tests the submitoDatadog function.
   */
  public function testSubmitToDatadog() {
    $configFactoryMock = $this->createMock(ConfigFactoryInterface::class);
    $configMock = $this->getMockBuilder(ImmutableConfig::class)
      ->disableOriginalConstructor()
      ->getMock();

    $configMock
      ->method('get')
      ->will(
        $this->returnValueMap(
          [
            ['api_key', '1234abc'],
            ['region', 'eu'],
          ]
        )
      );

    $configFactoryMock->expects($this->never())
      ->method('get')
      ->with('datadog.settings')
      ->willReturn($configMock);

    $configMock->expects($this->exactly(2))
      ->method('get');

    $container = [];
    $history = Middleware::history($container);

    $mock = new MockHandler([
      new Response(200),
    ]);

    $handlerStack = HandlerStack::create($mock);

    $handlerStack->push($history);

    $client = new Client(['handler' => $handlerStack]);

    $monitoringInformation = [
      [
        'metric_name' => 'testmetric1',
        'metric_type' => MetricTypesInterface::COUNT,
        'metric_value' => 1,
        'metric_tags' => [
          'tag1:value1',
        ],
      ],
      [
        'metric_name' => 'testmetric2',
        'metric_type' => MetricTypesInterface::GAUGE,
        'metric_value' => 2,
        'metric_tags' => [
          'tag2:value2',
        ],
      ],

    ];

    $expectedBody = [
      'series' => [
        [
          "metric" => 'testmetric1',
          "type" => MetricTypesInterface::COUNT,
          "points" => [
            [
              "timestamp" => $this->timeStamp,
              "value" => 1,
            ],
          ],
          "tags" => [
            'tag1:value1',
          ],
        ],
        [
          "metric" => 'testmetric2',
          "type" => MetricTypesInterface::GAUGE,
          "points" => [
            [
              "timestamp" => $this->timeStamp,
              "value" => 2,
            ],
          ],
          "tags" => [
            'tag2:value2',
          ],
        ],
      ],
    ];

    $this->monitoringUtilsMock->submitToDatadog($monitoringInformation, $configMock, $client);

    foreach ($container as $transaction) {
      $actualBody = $transaction['request']->getBody()->getContents();
    }

    $this->assertEquals(json_encode($expectedBody), $actualBody);
  }

}
