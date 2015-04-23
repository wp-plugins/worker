<?php

/**
 * Use with {@link OAuth1Upgrader} to convert old OAuth 1 access tokens
 * to OAuth 2 access tokens.  This SDK doesn't support using OAuth 1
 * access tokens for regular API calls.
 */
class Dropbox_OAuth1AccessToken
{
    /**
     * The OAuth 1 access token key.
     *
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /** @var string */
    private $key;

    /**
     * The OAuth 1 access token secret.
     *
     * Make sure that this is kept a secret.  Someone with your app secret can impesonate your
     * application.  People sometimes ask for help on the Dropbox API forums and
     * copy/paste code that includes their app secret.  Do not do that.
     *
     * @return string
     */
    public function getSecret()
    {
        return $this->secret;
    }

    /** @var string */
    private $secret;

    /**
     * Constructor.
     *
     * @param string $key
     *                       {@link getKey()}
     * @param string $secret
     *                       {@link getSecret()}
     */
    public function __construct($key, $secret)
    {
        Dropbox_AppInfo::checkKeyArg($key);
        Dropbox_AppInfo::checkSecretArg($secret);

        $this->key    = $key;
        $this->secret = $secret;
    }

    /**
     * Use this to check that a function argument is of type <code>AppInfo</code>
     *
     * @internal
     */
    public static function checkArg($argName, $argValue)
    {
        if (!($argValue instanceof self)) {
            Dropbox_Checker::throwError($argName, $argValue, __CLASS__);
        }
    }
}
