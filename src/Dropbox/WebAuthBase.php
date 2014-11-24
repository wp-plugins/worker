<?php

/**
 * The base class for the two auth options.
 */
class Dropbox_WebAuthBase extends Dropbox_AuthBase
{
    protected function _getAuthorizeUrl($redirectUri, $state)
    {
        return Dropbox_RequestUtil::buildUrlForGetOrPut(
            $this->userLocale,
            $this->appInfo->getHost()->getWeb(),
            "1/oauth2/authorize",
            array(
                "client_id"     => $this->appInfo->getKey(),
                "response_type" => "code",
                "redirect_uri"  => $redirectUri,
                "state"         => $state,
            ));
    }

    protected function _finish($code, $originalRedirectUri)
    {
        // This endpoint requires "Basic" auth.
        $clientCredentials = $this->appInfo->getKey().":".$this->appInfo->getSecret();
        $authHeaderValue   = "Basic ".base64_encode($clientCredentials);

        $response = Dropbox_RequestUtil::doPostWithSpecificAuth(
            $this->clientIdentifier, $authHeaderValue, $this->userLocale,
            $this->appInfo->getHost()->getApi(),
            "1/oauth2/token",
            array(
                "grant_type"   => "authorization_code",
                "code"         => $code,
                "redirect_uri" => $originalRedirectUri,
            ));

        if ($response->statusCode !== 200) {
            throw Dropbox_RequestUtil::unexpectedStatus($response);
        }

        $parts = Dropbox_RequestUtil::parseResponseJson($response->body);

        if (!array_key_exists('token_type', $parts) or !is_string($parts['token_type'])) {
            throw new Dropbox_Exception_BadResponse("Missing \"token_type\" field.");
        }
        $tokenType = $parts['token_type'];
        if (!array_key_exists('access_token', $parts) or !is_string($parts['access_token'])) {
            throw new Dropbox_Exception_BadResponse("Missing \"access_token\" field.");
        }
        $accessToken = $parts['access_token'];
        if (!array_key_exists('uid', $parts) or !is_string($parts['uid'])) {
            throw new Dropbox_Exception_BadResponse("Missing \"uid\" string field.");
        }
        $userId = $parts['uid'];

        if ($tokenType !== "Bearer" && $tokenType !== "bearer") {
            throw new Dropbox_Exception_BadResponse("Unknown \"token_type\"; expecting \"Bearer\", got  "
                .Dropbox_Client::q($tokenType));
        }

        return array($accessToken, $userId);
    }
}
