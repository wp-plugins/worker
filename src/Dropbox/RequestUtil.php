<?php

/**
 * @internal
 */
final class Dropbox_RequestUtil
{
    /**
     * @param string $userLocale
     * @param string $host
     * @param string $path
     * @param array  $params
     *
     * @return string
     */
    public static function buildUrlForGetOrPut($userLocale, $host, $path, $params = null)
    {
        $url = self::buildUri($host, $path);
        $url .= "?locale=".rawurlencode($userLocale);

        if ($params !== null) {
            foreach ($params as $key => $value) {
                Dropbox_Checker::argStringNonEmpty("key in 'params'", $key);
                if ($value !== null) {
                    if (is_bool($value)) {
                        $value = $value ? "true" : "false";
                    } elseif (is_int($value)) {
                        $value = (string) $value;
                    } elseif (!is_string($value)) {
                        throw new InvalidArgumentException("params['$key'] is not a string, int, or bool");
                    }
                    $url .= "&".rawurlencode($key)."=".rawurlencode($value);
                }
            }
        }

        return $url;
    }

    /**
     * @param string $host
     * @param string $path
     *
     * @return string
     */
    public static function buildUri($host, $path)
    {
        Dropbox_Checker::argStringNonEmpty("host", $host);
        Dropbox_Checker::argStringNonEmpty("path", $path);

        return "https://".$host."/".$path;
    }

    /**
     * @param string $clientIdentifier
     * @param string $url
     *
     * @return Dropbox_Curl
     */
    public static function mkCurl($clientIdentifier, $url)
    {
        $curl = new Dropbox_Curl($url);

        $curl->set(CURLOPT_CONNECTTIMEOUT, 10);

        // If the transfer speed is below 1kB/sec for 10 sec, abort.
        $curl->set(CURLOPT_LOW_SPEED_LIMIT, 1024);
        $curl->set(CURLOPT_LOW_SPEED_TIME, 10);

        //$curl->set(CURLOPT_VERBOSE, true);  // For debugging.
        // TODO: Figure out how to encode clientIdentifier (urlencode?)
        $curl->addHeader("User-Agent: ".$clientIdentifier." Dropbox-PHP-SDK");

        return $curl;
    }

    /**
     * @param string $clientIdentifier
     * @param string $url
     * @param string $authHeaderValue
     *
     * @return Dropbox_Curl
     */
    public static function mkCurlWithAuth($clientIdentifier, $url, $authHeaderValue)
    {
        $curl = self::mkCurl($clientIdentifier, $url);
        $curl->addHeader("Authorization: $authHeaderValue");

        return $curl;
    }

    /**
     * @param string $clientIdentifier
     * @param string $url
     * @param string $accessToken
     *
     * @return Dropbox_Curl
     */
    public static function mkCurlWithOAuth($clientIdentifier, $url, $accessToken)
    {
        return self::mkCurlWithAuth($clientIdentifier, $url, $accessToken);
    }

    public static function buildPostBody($params)
    {
        if ($params === null) {
            return "";
        }

        $pairs = array();
        foreach ($params as $key => $value) {
            Dropbox_Checker::argStringNonEmpty("key in 'params'", $key);
            if ($value !== null) {
                if (is_bool($value)) {
                    $value = $value ? "true" : "false";
                } elseif (is_int($value)) {
                    $value = (string) $value;
                } elseif (!is_string($value)) {
                    throw new InvalidArgumentException("params['$key'] is not a string, int, or bool");
                }
                $pairs[] = rawurlencode($key)."=".rawurlencode((string) $value);
            }
        }

        return implode("&", $pairs);
    }

    /**
     * @param string     $clientIdentifier
     * @param string     $accessToken
     * @param string     $userLocale
     * @param string     $host
     * @param string     $path
     * @param array|null $params
     *
     * @return Dropbox_HttpResponse
     *
     * @throws Dropbox_Exception
     */
    public static function doPost($clientIdentifier, $accessToken, $userLocale, $host, $path, $params = null)
    {
        Dropbox_Checker::argStringNonEmpty("accessToken", $accessToken);

        $url = self::buildUri($host, $path);

        if ($params === null) {
            $params = array();
        }
        $params['locale']             = $userLocale;

        $curl = self::mkCurlWithOAuth($clientIdentifier, $url, $accessToken);
        $curl->set(CURLOPT_POST, true);
        $curl->set(CURLOPT_POSTFIELDS, self::buildPostBody($params));

        $curl->set(CURLOPT_RETURNTRANSFER, true);

        return $curl->exec();
    }

    /**
     * @param string     $clientIdentifier
     * @param string     $authHeaderValue
     * @param string     $userLocale
     * @param string     $host
     * @param string     $path
     * @param array|null $params
     *
     * @return Dropbox_HttpResponse
     *
     * @throws Dropbox_Exception
     */
    public static function doPostWithSpecificAuth($clientIdentifier, $authHeaderValue, $userLocale, $host, $path, $params = null)
    {
        Dropbox_Checker::argStringNonEmpty("authHeaderValue", $authHeaderValue);

        $url = self::buildUri($host, $path);

        if ($params === null) {
            $params = array();
        }
        $params['locale']             = $userLocale;

        $curl = self::mkCurlWithAuth($clientIdentifier, $url, $authHeaderValue);
        $curl->set(CURLOPT_POST, true);
        $curl->set(CURLOPT_POSTFIELDS, self::buildPostBody($params));

        $curl->set(CURLOPT_RETURNTRANSFER, true);

        return $curl->exec();
    }

    /**
     * @param string     $clientIdentifier
     * @param string     $accessToken
     * @param string     $userLocale
     * @param string     $host
     * @param string     $path
     * @param array|null $params
     *
     * @return Dropbox_HttpResponse
     *
     * @throws Dropbox_Exception
     */
    public static function doGet($clientIdentifier, $accessToken, $userLocale, $host, $path, $params = null)
    {
        Dropbox_Checker::argStringNonEmpty("accessToken", $accessToken);

        $url = self::buildUrlForGetOrPut($userLocale, $host, $path, $params);

        $curl = self::mkCurlWithOAuth($clientIdentifier, $url, $accessToken);
        $curl->set(CURLOPT_HTTPGET, true);
        $curl->set(CURLOPT_RETURNTRANSFER, true);

        return $curl->exec();
    }

    /**
     * @param string $responseBody
     *
     * @return mixed
     * @throws Dropbox_Exception_BadResponse
     */
    public static function parseResponseJson($responseBody)
    {
        $obj = json_decode($responseBody, true);
        if ($obj === null) {
            throw new Dropbox_Exception_BadResponse("Got bad JSON from server: $responseBody");
        }

        return $obj;
    }

    /**
     * @param Dropbox_HttpResponse $httpResponse
     *
     * @return Dropbox_Exception
     */
    public static function unexpectedStatus($httpResponse)
    {
        $sc = $httpResponse->statusCode;

        $message = "HTTP status $sc";
        if (is_string($httpResponse->body)) {
            if (($info = json_decode($httpResponse->body, true)) && isset($info['error'])) {
                $message .= ' '.$info['error'];
            } else {
                $message .= "\n".$httpResponse->body;
            }
        }

        if ($sc === 400) {
            return new Dropbox_Exception_BadRequest($message);
        }
        if ($sc === 401) {
            return new Dropbox_Exception_InvalidAccessToken($message);
        }
        if ($sc === 500 || $sc === 502) {
            return new Dropbox_Exception_ServerError($message);
        }
        if ($sc === 503) {
            return new Dropbox_Exception_RetryLater($message);
        }

        return new Dropbox_Exception_BadResponseCode("Unexpected $message", $sc);
    }

    /**
     * @param int $maxRetries
     *                        The number of times to retry it the action if it fails with one of the transient
     *                        API errors.  A value of 1 means we'll try the action once and if it fails, we
     *                        will retry once.
     *
     * @param callable $action
     *                         The the action you want to retry.
     *
     * @return mixed
     *               Whatever is returned by the $action callable.
     */
    public static function runWithRetry($maxRetries, Dropbox_Closure_ReRunnableActionInterface $action)
    {
        Dropbox_Checker::argNat("maxRetries", $maxRetries);

        $retryDelay = 1;
        $numRetries = 0;
        while (true) {
            try {
                return $action->run();
            } // These exception types are the ones we think are possibly transient errors.
            catch (Dropbox_Exception_NetworkIO $ex) {
                $savedEx = $ex;
            } catch (Dropbox_Exception_ServerError $ex) {
                $savedEx = $ex;
            } catch (Dropbox_Exception_RetryLater $ex) {
                $savedEx = $ex;
            }

            // We maxed out our retries.  Propagate the last exception we got.
            if ($numRetries >= $maxRetries) {
                throw $savedEx;
            }

            $numRetries++;
            sleep($retryDelay);
            $retryDelay *= 2;  // Exponential back-off.
        }
        throw new RuntimeException("unreachable");
    }
}
