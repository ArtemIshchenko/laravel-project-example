<?php
namespace App\Service;

use App\Models\User;
use Google\Service\Webmasters\SearchAnalyticsQueryRequest;
use Illuminate\Http\Request;

class GoogleSearchConsoleService
{

    private $client;
    private $request;
    private $webmastersService;


    public function __construct(Request $request, $integration = null)
    {
        $credential = base_path() . '/client_secret.json';
        $this->client = new \Google_Client();
        $this->client->setAuthConfig($credential);
        if ($integration) {
            $this->client->setAccessToken($integration->params['gsca']);
        }
        $this->client->addScope(\Google\Service\Webmasters::WEBMASTERS_READONLY);
        $this->client->addScope(\Google\Service\Webmasters::WEBMASTERS);
        $this->client->setAccessType('offline');

        if ($integration && $this->client->isAccessTokenExpired()) {
            $newAccessToken = $this->client->fetchAccessTokenWithRefreshToken($integration->params['gscr']);
            $this->client->setAccessToken($newAccessToken);
        }
        $this->webmastersService = new \Google_Service_Webmasters($this->client);
        $this->request = $request;
    }

    public function checkConnection() {
        return (bool) $this->webmastersService->sites->listSites()->getSiteEntry();
    }

    public function siteList() {
        return $this->webmastersService->sites->listSites()->getSiteEntry();
    }

    public function addSite($url) {
        return $this->webmastersService->sites->add($url);
    }

    public function querySearchAnalitic($siteUrl, $params) {
        $searchAnalyticsQueryRequest = new SearchAnalyticsQueryRequest();
        $searchAnalyticsQueryRequest->startDate = $params['startDate'];
        $searchAnalyticsQueryRequest->endDate = $params['endDate'];
        //$searchAnalyticsQueryRequest->dimensions = $params['dimensions'];

        return $this->webmastersService->searchanalytics->query($siteUrl, $searchAnalyticsQueryRequest)->getRows();
    }

    public function getAuthUrl() {
        $this->client->setPrompt('consent');
        $this->client->setState('sample_passthrough_value');
        $this->client->setRedirectUri(route('integrations.gsc-token'));

        return $this->client->createAuthUrl();
    }

    public function getToken() {
        if ($this->request->code && $this->request->state) {
            $this->client->setRedirectUri(route('integrations.gsc-token'));
            return $this->client->fetchAccessTokenWithAuthCode($this->request->code);
        }
        return false;
    }

    public function getClient() {
        return $this->client;
    }
}
