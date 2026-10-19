<?php

namespace App\Services;

use DocuSign\eSign\Client\ApiClient;
use DocuSign\eSign\Configuration;
use Exception;

class DocuSignService
{
    protected $apiClient;
    protected $accountId;
    protected $baseUri;
    protected $webhookSecret;

    private ?int $organizationId = null;

    public function __construct()
    {
        $this->organizationId = auth()->user()?->organization_id;
    }

    private function credential(string $name, $default = null)
    {
        $query = \App\Models\ApiCredential::withoutGlobalScopes()->where('provider', 'docusign')->where('name', $name)->where('is_active', true);
        if ($this->organizationId !== null) {
            $local = (clone $query)->where('organization_id', $this->organizationId)->first();
            if ($local && $local->key) return $local->key;
        }
        return (clone $query)->whereNull('organization_id')->first()?->key ?: $default;
    }

    public static function validateBaseUri(string $uri): string
    {
        $uri = rtrim($uri, '/');
        // Only HTTPS provider origins, never paths, credentials, ports or arbitrary hosts.
        if (!preg_match('~^https://[a-z0-9-]+\.docusign\.net$~D', $uri)) {
            throw new Exception('Invalid signing API origin.');
        }
        return $uri;
    }

    protected function createClient(Configuration $configuration): ApiClient
    {
        return new ApiClient($configuration);
    }

    public function getAccessToken(?int $organizationId = null)
    {
        if (func_num_args() > 0) $this->organizationId = $organizationId;
        $this->accountId = $this->credential('ACCOUNT_ID', config('services.docusign.account_id'));
        $this->baseUri = self::validateBaseUri((string) $this->credential('BASE_URI', config('services.docusign.base_uri', 'https://demo.docusign.net')));
        $oauthHost = $this->baseUri === 'https://demo.docusign.net' ? 'account-d.docusign.com' : 'account.docusign.com';
        $clientId = $this->credential('CLIENT_ID', config('services.docusign.client_id'));
        $userId = $this->credential('IMPERSONATED_USER_ID', config('services.docusign.impersonated_user_id'));
        $key = $this->resolvePrivateKey();
        if (!$this->accountId || !$clientId || !$userId || !$key) throw new Exception('Signing configuration is incomplete.');

        $configuration = new Configuration();
        $configuration->setCurlConnectTimeout(10);
        $configuration->setCurlTimeout(60);
        $configuration->setHost($this->baseUri . '/restapi');
        $this->apiClient = $this->createClient($configuration);
        $this->apiClient->getOAuth()->setOAuthBasePath($oauthHost);
        try {
            $response = $this->apiClient->requestJWTUserToken($clientId, $userId, $key, 'signature impersonation', 60);
            $token = $response[0]['access_token'] ?? null;
            if (!is_string($token) || $token === '') throw new Exception('Missing token.');
            $info = $this->apiClient->getUserInfo($token);
            $account = collect($info[0]['accounts'] ?? [])->first(fn ($account) => (string) $account['account_id'] === (string) $this->accountId);
            if (!$account) throw new Exception('Configured account is not authorized.');
            $resolved = self::validateBaseUri((string) $account['base_uri']);
            if (($resolved === 'https://demo.docusign.net') !== ($oauthHost === 'account-d.docusign.com')) throw new Exception('Signing environment mismatch.');
            $this->baseUri = $resolved;
            $this->apiClient->getConfig()->setHost($resolved . '/restapi');
            return $token;
        } catch (\Throwable $exception) {
            // Provider exceptions may contain keys, URLs, tokens and recipient details.
            throw new Exception('Signing authentication failed. Ask your administrator to verify the account and consent.');
        }
    }

    /**
     * Get the API client with authentication
     */
    public function getApiClient()
    {
        $token = $this->getAccessToken();
        $this->apiClient->getConfig()->addDefaultHeader('Authorization', 'Bearer ' . $token);
        return $this->apiClient;
    }

    public function getAccountId()
    {
        return $this->accountId;
    }

    public function getBaseUri()
    {
        return $this->baseUri;
    }

    public function getWebhookSecret(?int $organizationId = null)
    {
        if (func_num_args() > 0) $this->organizationId = $organizationId;
        return $this->credential('WEBHOOK_SECRET', config('services.docusign.webhook_secret'));
    }

    protected function resolvePrivateKey(): ?string
    {
        $privateKey = $this->credential('PRIVATE_KEY');

        if ($privateKey) {
            return $privateKey;
        }

        $configuredPrivateKey = config('services.docusign.private_key');
        if ($configuredPrivateKey) {
            return $configuredPrivateKey;
        }

        $privateKeyB64 = config('services.docusign.private_key_b64');
        if ($privateKeyB64) {
            $decodedKey = base64_decode($privateKeyB64, true);
            if ($decodedKey !== false) {
                return $decodedKey;
            }
        }

        $privateKeyPath = config('services.docusign.private_key_path');
        if (! $privateKeyPath) {
            return null;
        }

        $candidatePaths = [
            base_path($privateKeyPath),
            storage_path(ltrim($privateKeyPath, '/')),
        ];

        foreach ($candidatePaths as $candidatePath) {
            if (file_exists($candidatePath)) {
                $resolvedKey = file_get_contents($candidatePath);
                if ($resolvedKey !== false && $resolvedKey !== '') {
                    return $resolvedKey;
                }
            }
        }

        return null;
    }
}
