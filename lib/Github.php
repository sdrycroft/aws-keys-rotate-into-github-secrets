<?php

namespace App;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use SodiumException;

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
     * @param string $secretType One of `actions` or `dependabot`
     * @param string $key
     * @param string $value
     * @return void
     * @throws GuzzleException
     * @throws SodiumException
     */
    public function setSecret(string $owner, string $repo, string $secretType, string $key, string $value)
    {
        $publicKey = $this->getRepositoryPublicKey($owner, $repo, $secretType);

        $encrypted_message = base64_encode(sodium_crypto_box_seal($value, base64_decode($publicKey['key'])));

        $this->client->put(
            "/repos/{$owner}/{$repo}/{$secretType}/secrets/{$key}",
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
     * @throws GuzzleException
     */
    private function getRepositoryPublicKey(string $owner, string $repo, string $secretType): array
    {
        if (empty($this->publicKeys["{$owner}/{$repo}/{$secretType}"])) {
            $publicKey = $this->client->get("/repos/{$owner}/{$repo}/{$secretType}/secrets/public-key");
            $this->publicKeys["{$owner}/{$repo}/{$secretType}"] = json_decode($publicKey->getBody()->getContents(), true);
        }

        return $this->publicKeys["{$owner}/{$repo}/{$secretType}"];
    }
}
