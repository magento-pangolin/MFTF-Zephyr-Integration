# MFTF-Zephyr-Integration

The purpose of this project is to synchronize mftf with page builder tests meta data in a release line with corresponding Jira / Zephyr records in the same release line.

## Installation

* Download and install Magento B2B from a git branch (**Do Not** install **composer** based B2B because it contains additional CBE extensions that will cause unnecessary side effects)
* Add the following in _require_ section of magento B2B root composer.json
```
    "magento/mftf-zephyr-integration": "1.0",
    "lesstif/php-jira-rest-client": "^1.19",
```
* Go to root of Magento B2B installation, run commands from console

```cd <Magento_B2B_Root_Dir>```

```composer update magento/mftf-zephyr-integration```


## Usage
For example, to synchronize Zephyr project MC from release line 2.3.x, run commands from console, 

```cd <Magento_B2B_Root_Dir>```

```php vendor/magento/mftf-zephyr-integration/mzi.php --dryRun=0 --project=MC --releaseLine=2.3.x --pbReleaseLine=PB1.0.x```

