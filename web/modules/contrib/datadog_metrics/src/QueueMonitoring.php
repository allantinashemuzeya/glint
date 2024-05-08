<?php

namespace Drupal\datadog_metrics;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\datadog_metrics\Utils\MetricTypesInterface;
use Drupal\datadog_metrics\Utils\MonitoringUtils;
use GuzzleHttp\ClientInterface;

/**
 * Get and submit queues information as metrics to datadog.
 */
class QueueMonitoring {
  use MonitoringUtils;

  /**
   * @var \Drupal\Core\Queue\QueueWorkerManagerInterface
   */
  public QueueWorkerManagerInterface $workerManager;

  /**
   * @var \Drupal\Core\Queue\QueueFactory
   */
  public QueueFactory $queueFactory;

  /**
   * @var \GuzzleHttp\ClientInterface
   */
  public ClientInterface $httpClient;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  public ConfigFactoryInterface $configFactory;

  /**
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  public ImmutableConfig $datadogConfig;

  /**
   * QueueMonitoring constructor.
   *
   * @param \Drupal\Core\Queue\QueueWorkerManagerInterface $workerManager
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   * @param \GuzzleHttp\ClientInterface $httpClient
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   */
  public function __construct(
    QueueWorkerManagerInterface $workerManager,
    QueueFactory $queueFactory,
    ClientInterface $httpClient,
    ConfigFactoryInterface $configFactory,
  ) {
    $this->workerManager = $workerManager;
    $this->queueFactory = $queueFactory;
    $this->httpClient = $httpClient;
    $this->configFactory = $configFactory;
    $this->datadogConfig = $configFactory->get('datadog.settings');
  }

  /**
   * Gets the monitoring information and submits it to datadog.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function queueMonitoring() {
    $queues = $this->getQueues();
    $monitoringInformation = $this->getMonitoringInformation($queues);
    $this->submitToDatadog($monitoringInformation, $this->datadogConfig, $this->httpClient);
  }

  /**
   * Gets the monitoring information.
   *
   * @param array $queues
   *
   * @return array
   */
  public function getMonitoringInformation(array $queues): array {
    $monitoringInformation = [];

    foreach (array_keys($queues) as $queueId) {
      $queue = $this->queueFactory->get($queueId);

      $metricTags = [
        "host:" . $this->datadogConfig->get('env'),
        "queue:" . $queueId,
      ];

      $monitoringInformation[] =
        $this->getMonitoringInformationEntry('queues.items', MetricTypesInterface::COUNT, $queue->numberOfItems(), $metricTags);

    }

    return $monitoringInformation;
  }

  /**
   * Gets all queues.
   *
   * @return array
   */
  public function getQueues(): array {
    $queues = [];
    foreach ($this->workerManager->getDefinitions() as $name => $definiton) {
      $queues[$name] = $definiton;
    }
    return $queues;
  }

}
