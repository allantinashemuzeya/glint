<?php

namespace Drupal\datadog_metrics\Utils;

/**
 * Metric types interface.
 */
interface MetricTypesInterface {
  const UNSPECIFIED = 0;
  const COUNT = 1;
  const RATE = 2;
  const GAUGE = 3;

}
