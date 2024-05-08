# Monolog Datadog

Uses a Monolog handler to send Logs to Datadog without a Datadog agent.
Also there's a processor that maps the log levels from Drupal to Datadogs log
status. Based on https://github.com/guspio/monolog-datadog and
https://github.com/nohponex/monolog-datadog-handler/blob/master/src/DataDogHandler.php.

## Usage

`docroot/sites/default/logging.services.yml`

```yaml
parameters:
  monolog.channel_handlers:
    default: ['datadog']
    php: ['error_log', 'datadog']
services:
  monolog.handler.datadog:
    class: Drupal\monolog_datadog\Monolog\Handler\DatadogHandler
    arguments: ['@config.factory', 'EU' ]
```

### API key

Set the API key in your `settings.php` like

```php
$config['monolog_datadog.settings']['api_key'] = 'yourSuperSecureAPIKey';
```

### Datadog Tags

Set tags like `env` or custom tags like `project` also in your `settings.php`

```php
$config['monolog_datadog.settings']['ddtags'] = 'env:production,project:liip.ch';
```

### Test the logging service
```
drush monolog_datadog:test-logging-services
```
