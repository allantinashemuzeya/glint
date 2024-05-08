# Datadog Metrics

This module uses a cron job to send metrics to Datadog.

## Features

The module sends the following metrics to Datadog:

 - `migration.status` (status of each migration)
 - `migration.items` (number of items in the destination table)
 - `migration.processed` (number of processed items in the last import)
 - `migration.created` (number of created items in the last import)
 - `migration.updated` (number of updated items in the last import)
 - `migration.deleted` (number of deleted items in the last import)
 - `queue.items` (number of items that are currently in the queue)

## Post-Installation
After the installation, you should add the following values to your Settings.php

```
$config['datadog.settings']['api_key'] = 'your-api-key'
$config['datadog.settings']['region'] = 'your-region'
$config['datadog.settings']['env'] = 'your-environment'
```

# Additional Requirements

This module requires the following libraries:

- `guzzlehttp/guzzle`
- `guzzlehttp/psr`
