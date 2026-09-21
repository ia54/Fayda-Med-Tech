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

    public function __construct()
    {
        $this->accountId = getApiCredential('docusign', 'ACCOUNT_ID', config('services.docusign.account_id'));
        $this->baseUri = rtrim(getApiCredential('docusign', 'BASE_URI', config('services.docusign.base_uri', 'https://demo.docusign.net')), '/');
        $this->webhookSecret = getApiCredential('docusign', 'WEBHOOK_SECRET', config('services.docusign.webhook_secret'));
        
        $config = new Configuration();
        $config->setHost($this->baseUri . '/restapi');
        $this->apiClient = new ApiClient($config);
    }

    /**
     * Get an access token using JWT
     */
    public function getAccessToken()
    {
        $clientId = getApiCredential('docusign', 'CLIENT_ID', config('services.docusign.client_id'));
        $impersonatedUserId = getApiCredential('docusign', 'IMPERSONATED_USER_ID', config('services.docusign.impersonated_user_id'));

        $privateKey = $this->resolvePrivateKey();
        
        if (!$clientId || !$impersonatedUserId || !$privateKey) {
            throw new Exception("DocuSign JWT configuration is incomplete. Check CLIENT_ID, IMPERSONATED_USER_ID, and PRIVATE_KEY.");
        }
        
        $scope = 'signature impersonation';
        
        try {
            $this->apiClient->getOAuth()->setOAuthBasePath('account-d.docusign.com');
            $response = $this->apiClient->requestJWTUserToken(
                $clientId,
                $impersonatedUserId,
                $privateKey,
                $scope,
                3600
            );
            
            $token = $response[0]['access_token'];

            // If accountId is not set, fetch it
            if (!$this->accountId) {
                $userInfo = $this->apiClient->getUserInfo($token);
                if (isset($userInfo[0]['accounts'][0]['account_id'])) {
                    $this->accountId = $userInfo[0]['accounts'][0]['account_id'];
                }
            }

            return $token;
        } catch (Exception $e) {
            // Check if consent is required
            if (strpos($e->getMessage(), 'consent_required') !== false) {
                $authUrl = "https://account-d.docusign.com/oauth/auth?prompt=login&response_type=code&scope=" . urlencode($scope) . "&client_id=" . $clientId . "&redirect_uri=" . urlencode(config('app.url'));
                throw new Exception("Consent required. Please visit: " . $authUrl);
            }
            throw $e;
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

    public function getWebhookSecret()
    {
        return $this->webhookSecret;
    }

    protected function resolvePrivateKey(): ?string
    {
        $privateKey = getApiCredential('docusign', 'PRIVATE_KEY');

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
