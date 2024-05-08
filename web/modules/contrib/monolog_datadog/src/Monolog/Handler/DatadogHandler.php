<?php

declare(strict_types=1);

namespace Drupal\monolog_datadog\Monolog\Handler;

use Drupal\Core\Config\ConfigFactory;
use Drupal\monolog_datadog\Monolog\Processor\LevelProcessor;
use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Handler\Curl\Util;
use Monolog\Logger;
use Drupal\Core\Config\ImmutableConfig;

/**
 * Sends logs to Datadog Logs using Curl integrations.
 *
 * You'll need a Datadog account to use this handler.
 */
class DatadogHandler extends AbstractProcessingHandler {
  /**
   * Datadog hosts.
   *
   * @var string
   */
  protected const DATADOG_LOG_HOSTS = [
    'US' => 'https://http-intake.logs.datadoghq.com/api/v2/logs',
    'US3' => 'https://http-intake.logs.us3.datadoghq.com/api/v2/logs',
    'US5' => 'https://http-intake.logs.us5.datadoghq.com/api/v2/logs',
    'EU' => 'https://http-intake.logs.datadoghq.eu/api/v2/logs',
    'US1-FED' => 'https://http-intake.logs.ddog-gov.com/api/v2/logs',
  ];

  /**
   * Config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private ImmutableConfig $config;

  /**
   * Datadog Api Key access.
   *
   * @var string
   */
  private string $apiKey;

  /**
   * Datadog region (Possible regions: US, US3, US5, EU, US1-FED).
   *
   * @var string
   */
  private string $region;

  /**
   * Datadog optionals attributes.
   *
   * @var array
   */
  private array $attributes;

  /**
   * Datadog tags (ddtags).
   *
   * @var string
   */
  private string $ddtags;

  /**
   * Constructor.
   *
   * @param string $region
   *   Datadog region (Possible regions: US, US3, US5, EU, US1-FED).
   * @param \Drupal\Core\Config\ConfigFactory $config
   *   Config.
   * @param array $attributes
   *   Some options fore Datadog Logs.
   * @param string|int $level
   *   The minimum logging level at which this handler will be triggered.
   * @param bool $bubble
   *   Whether the messages that are handled can bubble up the stack or not.
   */
  public function __construct(
        ConfigFactory $config,
        string $region = 'US',
        array $attributes = [],
               $level = Logger::DEBUG,
        bool $bubble = TRUE
    ) {
    if (!extension_loaded('curl')) {
      throw new MissingExtensionException('The curl extension is needed to use the DatadogHandler');
    }

    parent::__construct($level, $bubble);
    $this->region = $region;
    $this->config = $config->get('monolog_datadog.settings');
    $this->apiKey = $this->config->get('api_key');
    $this->attributes = $attributes;
    $this->ddtags = $this->config->get('ddtags');
    $this->pushProcessor(new LevelProcessor());

  }

  /**
   * Handles a log record.
   *
   * @param array $record
   *   The record.
   */
  protected function write(array $record): void {
    $this->send($record['formatted']);
  }

  /**
   * Send request to https://http-intake.logs.datadoghq.com on send action.
   *
   * @param string $record
   *   The record.
   */
  protected function send(string $record): void {
    $headers = [
      'Content-Type:application/json',
      sprintf('DD-API-KEY:%s', $this->apiKey),
    ];

    $source = $this->getSource();
    $hostname = $this->getHostname();
    $service = $this->getService($record);

    if (!in_array($this->region, array_flip(self::DATADOG_LOG_HOSTS))) {
      throw new \Exception('Invalid region argument.');
    }

    $url = sprintf(
          '%s?ddsource=%s&service=%s&hostname=%s&ddtags=%s',
          self::DATADOG_LOG_HOSTS[$this->region],
          $source,
          $service,
          $hostname,
          $this->ddtags
      );

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $record);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);

    Util::execute($ch);
  }

  /**
   * Get Datadog Api Key from $attributes params.
   *
   * @param string $apiKey
   *   The api key.
   */
  protected function getApiKey(string $apiKey): string {
    if ($apiKey) {
      return $apiKey;
    }
    else {
      throw new \Exception('The Datadog Api Key is required');
    }
  }

  /**
   * Get Datadog Source from $attributes params.
   *
   * @return mixed|string
   *   Mixed.
   */
  protected function getSource() {
    return !empty($this->attributes['source']) ? $this->attributes['source'] : 'php';
  }

  /**
   * Get service.
   *
   * @param string $record
   *   The record.
   *
   * @return mixed
   *   Mixed.
   */
  protected function getService(string $record) {
    $channel = json_decode($record, TRUE);

    return !empty($this->attributes['service']) ? $this->attributes['service'] : $channel['channel'];
  }

  /**
   * Get Datadog Hostname from $attributes params.
   */
  protected function getHostname() {
    return !empty($this->attributes['hostname']) ? $this->attributes['hostname'] : $_SERVER['SERVER_NAME'];
  }

  /**
   * Returns the default formatter to use with this handler.
   *
   * @return \Monolog\Formatter\JsonFormatter
   *   Returns formatter.
   */
  protected function getDefaultFormatter(): FormatterInterface {
    return new JsonFormatter();
  }

}
