# PHP Webhook Updater

Webhooks PHP GitHub Updater,
Multiple folders designed under the same user/domain,
with status page, logs and database, no password login,
designed for custom hosting based on Nginx and PHP-fpm,
Web construction based on bootstrap 5.2.3 CDN

## Considerations

Initial scenery is Linux server, Nginx and PHP-fpm services, virtual user for client/domain deployment, central administration based on ssh server access

## Install

- In the user/domain folder, create a folder to install this package,
use *git clone* to copy this repository or extract zip files from the download.
- Run *composer install* in the root directory to create the appropriate *vendor* folder with dependencies.
- Create a site on your Nginx web server as *github-webhook.domain*
or another subdomain of your preference,
publish to the public subfolder as web root.
- Create an appropriate user and database (MariaDB/Mysql).
- Copy .env-sample to .env
- Full .env file with all values
- Go to scripts and run *php install.php*
- Use *scripts/console.php* to config your first folder
- Config you repository webhooks using https://github.com/{user}/{repository}/settings/hooks
- Config ssh keys using https://github.com/{user}/{repository}/settings/keys for enable individual repo security, or use https://github.com/settings/keys to stablish a general key for your server
- Make sure the ssh key is enabled on the corresponding web user
- Please re-check your platform before send a issue

## Admin

Go to scripts and use console.php CLI interface
You can list create, list, update and delete repositories
You can list and delete tokens

## Usage

Go to your subdomain/status.php
After resolve login flow you can see the last status for all repositories

## Future

Add more details on status page
Generate database alternatives, like SQLite or Postgres
Enable GitLab webhooks integration

## Donations

PayPal (Donations page)[https://www.paypal.com/donate/?business=GJS2KEGB5XG76&no_recurring=0&item_name=El+c%C3%B3digo+siempre+puede+mejorar%2C+es+un+trabajo+constante%2C+por+eso+siempre+agradecer%C3%A9+tu+apoyo+de+coraz%C3%B3n&currency_code=USD]
