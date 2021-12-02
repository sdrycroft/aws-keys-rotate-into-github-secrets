<?php

require 'vendor/autoload.php';

use App\Github;
use Aws\Iam\IamClient;
use GetOpt\GetOpt;
use GetOpt\Option;
use Symfony\Component\Yaml\Yaml;

$options = new GetOpt(
    [
        Option::create('c', 'config-file', GetOpt::REQUIRED_ARGUMENT)
            ->setDefaultValue('rotate.yml')
            ->setDescription('Relative or absolute path to YAML file that has the settings for rotate'),
    ]
);
try {
    $options->process();
} catch (Exception $exception) {
    echo $options->getHelpText();
    exit(1);
}

$configFile = $options->getOption('config-file');

if ($options->getOption('help') || !$configFile) {
    echo $options->getHelpText();
    exit;
}
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

    echo "\n\nCreate a configuration like the one above.\n";
    exit(0);
}

$config = Yaml::parseFile($configFile);

// Check that the contents of the configuration have been updated.
if ($config['USER'] === 'user' || $config['KEY'] === 'key' || $config['secret'] === 'secret') {
    echo "Please update your configuration before running again.\n";
    exit(1);
};

// Github client
$github = new Github($config['github']['token']);

foreach ($config['keys'] as $keyName => $keyData) {
    $iamClient = new IamClient($keyData['aws']['config']);

    $keys = $iamClient->listAccessKeys(['user' => $keyData['aws']['user']])->get('AccessKeyMetadata');

    // We should have one key, if we don't, something has gone wrong and we need to recover.
    if (count($keys) !== 1) {
        echo "Incorrect number of access keys, bailing.\n";
        exit(1);
    }
    $oldKey = $keys[0];

    // 0. Check that the key is older than our specified time interval.
    $now = new DateTime();
    if ($now->sub(new DateInterval($keyData['aws']['maxKeyAge'] ?: "P7D")) > $oldKey['CreateDate']) {
        // 1. Create a new key.
        $newKey = $iamClient->createAccessKey(['user' => $keyData['aws']['user']]);
        $newKey = $newKey->get('AccessKey');

        // 2. Update the configuration and write it out.
        $config['keys'][$keyName]['aws']['config']['credentials']['key'] = $newKey['AccessKeyId'];
        $config['keys'][$keyName]['aws']['config']['credentials']['secret'] = $newKey['SecretAccessKey'];
        file_put_contents($configFile, Yaml::dump($config, 10));

        // 3. Set Github Secrets.
        foreach ($keyData['keyDestinations'] as $keyDestination) {
            $github->setSecret(
                $keyDestination['owner'],
                $keyDestination['repo'],
                $keyDestination['key'],
                $newKey['AccessKeyId']
            );
        }
        foreach ($keyData['secretDestinations'] as $secretDestination) {
            $github->setSecret(
                $secretDestination['owner'],
                $secretDestination['repo'],
                $secretDestination['key'],
                $newKey['SecretAccessKey']
            );
        }

        // 4. Delete old key.
        $iamClient->deleteAccessKey(
            [
                'AccessKeyId' => $oldKey['AccessKeyId'],
                'UserName' => $keyData['aws']['user'],
            ]
        );
    }
}
