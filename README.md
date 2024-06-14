### Mautic Postal Plugin

This plugin enable Mautic 5 to send emails via SMTP transport and receives Bounces & Failures via Webhooks.

### Installation

Clone repo.

```
git clone <repo-url> PostalBundle
```

Install the plugin

```
rm -rf var/cache/dev/* var/cache/prod/*
php bin/console mautic:plugins:reload --env=prod
```

### Postal Configuration

Add a webhook on your postal server configuration for `MessageDeliveryFailed` and `MesssageBounced` to following url:

    https://mautic.yourdomain/mailer/callback

### Testing

To run all tests `composer phpunit`

To run unit tests `composer unit`

To run functional tests `composer functional`

### Static analysis tools

To run fixes by friendsofphp/php-cs-fixer `composer fixcs`

To run phpstan `composer phpstan`