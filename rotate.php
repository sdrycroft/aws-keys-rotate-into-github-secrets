<?php

require 'vendor/autoload.php';

use App\Github;
use Aws\Iam\IamClient;
use GetOpt\GetOpt;
use Symfony\Component\Yaml\Yaml;

// Process command arguments.
try {
    $config = new GetOpt([
        ['c', 'config', GetOpt::REQUIRED_ARGUMENT, 'Relative or absolute path to YAML file that has the settings for rotate', 'rotate.yml'],
        ['v', 'verbose', GetOpt::NO_ARGUMENT],
    ]);
    $config->process();
    $configFile = $config->getOption('config');
    $verbose = $config->getOption('verbose');
} catch (Exception $exception) {
    echo "An error occurred processing your arguments".PHP_EOL;
    exit(1);
}

// Output help if that was asked for.
if ($config->getOption('help') || !$configFile) {
    echo $config->getHelpText();
    exit;
}

// Change the path to be an absolute one if we were given a relative one.
if (substr($configFile, 0, 1) !== '/') {
    $configFile = "{$_ENV['PWD']}/$configFile";
}

if (!file_exists($configFile)) {
    $config = [
        'github' => [
            'token' => 'token-here',
        ],
        'keys' => [
            'int' => [
                'aws' => [
                    'user' => 'ofr-automation',
                    'maxKeyAge' => 'P7D',
                    'config' => [
                        'region' => 'eu-west-2',
                        'version' => 'latest',
                        'credentials' => [
                            'key' => 'key',
                            'secret' => 'secret',
                        ],
                    ],
                ],
                'keyDestinations' => [
                    [
                        'owner' => 'owner',
                        'repo' => 'repo',
                        'key' => 'AWS_ACCESS_KEY_ID',
                    ],
                ],
                'secretDestinations' => [
                    [
                        'owner' => 'owner',
                        'repo' => 'repo',
                        'key' => 'AWS_SECRET_ACCESS_KEY',
                    ],
                ],
            ],
        ],
    ];

    echo Yaml::dump($config, 10);

    echo PHP_EOL.PHP_EOL."Create a configuration like the one above.".PHP_EOL;
    exit(0);
}

// Check we can write to the configuration file.
if (!is_writeable($configFile)) {
    echo "Configuration file is not writable.".PHP_EOL;
    exit(1);
}
$config = Yaml::parseFile($configFile);

// Check that the contents of the configuration have been updated.
if ($config['USER'] === 'user' || $config['KEY'] === 'key' || $config['secret'] === 'secret') {
    echo "Please update your configuration before running again.".PHP_EOL;
    exit(1);
};

// Github client
$github = new Github($config['github']['token']);

foreach ($config['keys'] as $keyName => $keyData) {
    $iamClient = new IamClient($keyData['aws']['config']);

    $keys = $iamClient->listAccessKeys(['user' => $keyData['aws']['user']])->get('AccessKeyMetadata');

    // We should have one key, if we don't, something has gone wrong, and we need to recover.
    if (count($keys) !== 1) {
        echo "Incorrect number of access keys, bailing.".PHP_EOL;
        exit(1);
    }
    $oldKey = $keys[0];

    // 0. Check that the key is older than our specified time interval.
    $now = new DateTime();
    if ($now->sub(new DateInterval($keyData['aws']['maxKeyAge'] ?: "P7D")) > $oldKey['CreateDate']) {
        if ($verbose) {
            echo "Updating key '{$keyName}'".PHP_EOL;
        }
        // 1. Create a new key.
        $newKey = $iamClient->createAccessKey(['user' => $keyData['aws']['user']]);
        $newKey = $newKey->get('AccessKey');
        if ($verbose) {
            echo "Created new AWS Key for '{$keyData['aws']['user']}'".PHP_EOL;
        }

        // 2. Update the configuration and write it out.
        $config['keys'][$keyName]['aws']['config']['credentials']['key'] = $newKey['AccessKeyId'];
        $config['keys'][$keyName]['aws']['config']['credentials']['secret'] = $newKey['SecretAccessKey'];
        file_put_contents($configFile, Yaml::dump($config, 10));
        if ($verbose) {
            echo "Updated configuration file".PHP_EOL;
        }

        // 3. Set Github Secrets.
        foreach ($keyData['keyDestinations'] as $keyDestination) {
            try {
                $github->setSecret(
                    $keyDestination['owner'],
                    $keyDestination['repo'],
                    $keyDestination['key'],
                    $newKey['AccessKeyId']
                );
                if ($verbose) {
                    echo "Set {$keyDestination['owner']}/{$keyDestination['repo']} :: {$keyDestination['key']}".PHP_EOL;
                }
            } catch (Exception $exception) {
                echo "Unable to set {$keyDestination['owner']}/{$keyDestination['repo']} :: {$keyDestination['key']}".PHP_EOL;
            }
        }
        foreach ($keyData['secretDestinations'] as $secretDestination) {
            try {
                $github->setSecret(
                    $secretDestination['owner'],
                    $secretDestination['repo'],
                    $secretDestination['key'],
                    $newKey['SecretAccessKey']
                );
                if ($verbose) {
                    echo "Set {$secretDestination['owner']}/{$secretDestination['repo']} :: {$secretDestination['key']}".PHP_EOL;
                }
            } catch (Exception $exception) {
                echo "Unable to set {$secretDestination['owner']}/{$secretDestination['repo']} :: {$secretDestination['key']}".PHP_EOL;
            }
        }

        // 4. Delete old key.
        $iamClient->deleteAccessKey(
            [
                'AccessKeyId' => $oldKey['AccessKeyId'],
                'UserName' => $keyData['aws']['user'],
            ]
        );
        if ($verbose) {
            echo "Deleted AWS Key for '{$keyData['aws']['user']}'".PHP_EOL;
        }
    } else {
        if ($verbose) {
            echo "Key '{$keyName}' is already newer than required".PHP_EOL;
        }
    }
}
