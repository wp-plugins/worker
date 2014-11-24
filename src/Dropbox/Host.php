<?php

/**
 * The Dropbox web API accesses three hosts; this structure holds the
 * names of those three hosts.  This is primarily for mocking things out
 * during testing.  Most of the time you won't have to deal with this class
 * directly, and even when you do, you'll just use the default
 * value: {@link Host::getDefault()}.
 *
 * @internal
 */
final class Dropbox_Host
{
    /**
     * Returns a Host object configured with the three standard Dropbox host: "api.dropbox.com",
     * "api-content.dropbox.com", and "www.dropbox.com"
     *
     * @return Dropbox_Host
     */
    public static function getDefault()
    {
        if (!self::$defaultValue) {
            self::$defaultValue = new self("api.dropbox.com", "api-content.dropbox.com", "www.dropbox.com");
        }

        return self::$defaultValue;
    }

    private static $defaultValue;

    /** @var string */
    private $api;
    /** @var string */
    private $content;
    /** @var string */
    private $web;

    /**
     * Constructor.
     *
     * @param string $api
     *                        See {@link getApi()}
     * @param string $content
     *                        See {@link getContent()}
     * @param string $web
     *                        See {@link getWeb()}
     */
    public function __construct($api, $content, $web)
    {
        $this->api     = $api;
        $this->content = $content;
        $this->web     = $web;
    }

    /**
     * Returns the host name of the main Dropbox API server.
     * The default is "api.dropbox.com".
     *
     * @return string
     */
    public function getApi()
    {
        return $this->api;
    }

    /**
     * Returns the host name of the Dropbox API content server.
     * The default is "api-content.dropbox.com".
     *
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Returns the host name of the Dropbox web server.  Used during user authorization.
     * The default is "www.dropbox.com".
     *
     * @return string
     */
    public function getWeb()
    {
        return $this->web;
    }

    /**
     * Check that a function argument is of type <code>Host</code>.
     *
     * @internal
     */
    public static function checkArg($argName, $argValue)
    {
        if (!($argValue instanceof self)) {
            Dropbox_Checker::throwError($argName, $argValue, __CLASS__);
        }
    }

    /**
     * Check that a function argument is either <code>null</code> or of type
     * <code>Host</code>.
     *
     * @internal
     */
    public static function checkArgOrNull($argName, $argValue)
    {
        if ($argValue === null) {
            return;
        }
        if (!($argValue instanceof self)) {
            Dropbox_Checker::throwError($argName, $argValue, __CLASS__);
        }
    }
}
