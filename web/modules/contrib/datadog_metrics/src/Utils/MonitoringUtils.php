<?php

namespace Drupal\datadog_metrics\Utils;

use Drupal\Core\Config\Config;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;

/**
 * Some monitoring util functions.
 */
trait MonitoringUtils {

  /**
   * Returns a datadog series entry.
   *
   * @param $metricName
   * @param $metricType
   * @param $metricValue
   * @param $tags
   *
   * @return array
   */
  public function getDatadogSeriesEntry($metricName, $metricType, $metricValue, $tags): array {
    return [
      "metric" => $metricName,
      "type" => $metricType,
      "points" => [
        [
          "timestamp" => $this->getTime(),
          "value" => $metricValue,
        ],
      ],
      "tags" => $tags,
    ];
  }

  /**
   * Returns a monitoring information entry.
   *
   * @param $name
   * @param $type
   * @param $value
   * @param $tags
   *
   * @return array
   */
  public function getMonitoringInformationEntry($name, $type, $value, $tags): array {
    return [
      'metric_name' => $name,
      'metric_type' => $type,
      'metric_value' => $value,
      'metric_tags' => $tags,
    ];
  }

  /**
   * Submits the monitoring entries to datadog.
   *
   * @param $monitoringInformation
   * @param \Drupal\Core\Config\Config $datadogConfig
   * @param \GuzzleHttp\ClientInterface $httpClient
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function submitToDatadog($monitoringInformation, Config $datadogConfig, ClientInterface $httpClient): void {
    $data = [
      "series" => [],
    ];

    foreach ($monitoringInformation as $information) {
      $data["series"][] = $this->getDatadogSeriesEntry(
        $information['metric_name'],
        $information['metric_type'],
        $information['metric_value'],
        $information['metric_tags']
      );
    }

    $headers = [
      "Accept" => "application/json",
      "Content-Type" => "application/json",
      "DD-API-KEY" => $datadogConfig->get('api_key'),
    ];

    $httpClient->post("https://api.datadoghq." . $datadogConfig->get('region') . "/api/v2/series", [
      RequestOptions::HEADERS => $headers,
      RequestOptions::JSON => $data,
    ]);
  }

  /**
   * Gets the current timestamp.
   *
   * @return int
   */
  public function getTime(): int {
    return time();
  }

}
