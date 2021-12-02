<?php

namespace App;

use GuzzleHttp\Client;

/**
 * Class Github
 */
class Github
{
    /** Client */
    private $client;

    /** @var string[][] */
    private $publicKeys = [];

    /**
     * Github constructor.
     */
    public function __construct(string $token)
    {
        $this->client = new Client(
            [
                'headers' => [
                    'Authorization' => sprintf('Bearer %s', $token),
                ],
                'base_uri' => 'https://api.github.com/',
            ]
        );
    }

    /**
     * @param string $owner
     * @param string $repo
     * @param string $key
     * @param string $value
     */
    public function setSecret(string $owner, string $repo, string $key, string $value)
    {
        $publicKey = $this->getRepositoryPublicKey($owner, $repo);

        $encrypted_message = base64_encode(sodium_crypto_box_seal($value, base64_decode($publicKey['key'])));

        $this->client->put(
            "/repos/{$owner}/{$repo}/actions/secrets/{$key}",
            [
                'json' => [
                    "encrypted_value" => $encrypted_message,
                    "key_id" => $publicKey['key_id'],
                ],
            ]
        );
    }

    /**
     * @param string $owner
     * @param string $repo
     * @return string[]
     */
    private function getRepositoryPublicKey(string $owner, string $repo): array
    {
        if (empty($this->publicKeys["{$owner}/{$repo}"])) {
            $publicKey = $this->client->get("/repos/{$owner}/{$repo}/actions/secrets/public-key");
            $this->publicKeys["{$owner}/{$repo}"] = json_decode($publicKey->getBody()->getContents(), true);
        }

        return $this->publicKeys["{$owner}/{$repo}"];
    }
}
