# Mage2 Module OrviSoft Cloudburst

    `orvisoft/module-cloudburst`

 - [Main Functionalities](#main-functionalities)
 - [Installation](#installation)
 - [Configuration](#configuration)
 - [Specifications](#specifications)


## Main Functionalities
Integration middle-ware for Cloud Burst software.

## Installation
\* = in production please use the `--keep-generated` option

### Type 1: Zip file

 - Unzip the zip files in `app/code/OrviSoft/Cloudburst`
 - Enable the module by running `php bin/magento module:enable OrviSoft_Cloudburst`
 - Apply database updates by running `php bin/magento setup:upgrade`\*
 - Flush the cache by running `php bin/magento cache:flush`

### Type 2: Composer

 - Make the module available in a composer repository for example:
    - private repository `repo.magento.com`
    - public repository `packagist.org`
    - public github repository as vcs
 - Add the composer repository to the configuration by running `composer config repositories.repo.magento.com composer https://repo.magento.com/`
 - Install the module composer by running `composer require orvisoft/module-cloudburst`
 - enable the module by running `php bin/magento module:enable OrviSoft_Cloudburst`
 - apply database updates by running `php bin/magento setup:upgrade`\*
 - Flush the cache by running `php bin/magento cache:flush`


## Configuration

 - Status (cloudburst/options/is_active)

 - Cloud Burst Secret (cloudburst/options/burst_secret)

 - Callback URL (cloudburst/options/callback_url)

 - Error Notification (cloudburst/options/error_notification)


## Specifications

 - Helper
	- OrviSoft\Cloudburst\Helper\Data

