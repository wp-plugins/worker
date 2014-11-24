<?php

/**
 * Your app's API key and secret.
 */
final class Dropbox_AppInfo
{
    /**
     * Your Dropbox <em>app key</em> (OAuth calls this the <em>consumer key</em>).  You can
     * create an app key and secret on the <a href="http://dropbox.com/developers/apps">Dropbox developer website</a>.
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
     * Your Dropbox <em>app secret</em> (OAuth calls this the <em>consumer secret</em>).  You can
     * create an app key and secret on the <a href="http://dropbox.com/developers/apps">Dropbox developer website</a>.
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
     * The set of servers your app will use.  This defaults to the standard Dropbox servers
     * {@link Host::getDefault}.
     *
     * @return Dropbox_Host
     *
     * @internal
     */
    public function getHost()
    {
        return $this->host;
    }

    /** @var Dropbox_Host */
    private $host;

    /**
     * Constructor.
     *
     * @param string $key
     *                       See {@link getKey()}
     * @param string $secret
     *                       See {@link getSecret()}
     */
    public function __construct($key, $secret)
    {
        self::checkKeyArg($key);
        self::checkSecretArg($secret);

        $this->key    = $key;
        $this->secret = $secret;

        // The $host parameter is sort of internal.  We don't include it in the param list because
        // we don't want it to be included in the documentation.  Use PHP arg list hacks to get at
        // it.
        $host = null;
        if (func_num_args() == 3) {
            $host = func_get_arg(2);
            Dropbox_Host::checkArgOrNull("host", $host);
        }
        if ($host === null) {
            $host = Dropbox_Host::getDefault();
        }
        $this->host = $host;
    }

    /**
     * Loads a JSON file containing information about your app. At a minimum, the file must include
     * the "key" and "secret" fields.  Run 'php authorize.php' in the examples directory
     * for details about what this file should look like.
     *
     * @param string $path
     *                     Path to a JSON file
     *
     * @return Dropbox_AppInfo
     *
     * @throws Dropbox_AppInfoLoadException
     */
    public static function loadFromJsonFile($path)
    {
        list($rawJson, $appInfo) = self::loadFromJsonFileWithRaw($path);

        return $appInfo;
    }

    /**
     * Loads a JSON file containing information about your app. At a minimum, the file must include
     * the "key" and "secret" fields.  Run 'php authorize.php' in the examples directory
     * for details about what this file should look like.
     *
     * @param string $path
     *                     Path to a JSON file
     *
     * @return array
     *               A list of two items.  The first is a PHP array representation of the raw JSON, the second
     *               is an AppInfo object that is the parsed version of the JSON.
     *
     * @throws Dropbox_AppInfoLoadException
     *
     * @internal
     */
    public static function loadFromJsonFileWithRaw($path)
    {
        if (!file_exists($path)) {
            throw new Dropbox_AppInfoLoadException("File doesn't exist: \"$path\"");
        }

        $str     = file_get_contents($path);
        $jsonArr = json_decode($str, true);

        if (is_null($jsonArr)) {
            throw new Dropbox_AppInfoLoadException("JSON parse error: \"$path\"");
        }

        $appInfo = self::loadFromJson($jsonArr);

        return array($jsonArr, $appInfo);
    }

    /**
     * Parses a JSON object to build an AppInfo object.  If you would like to load this from a file,
     * use the loadFromJsonFile() method.
     *
     * @param array $jsonArr Output from json_decode($str, TRUE)
     *
     * @return Dropbox_AppInfo
     *
     * @throws Dropbox_AppInfoLoadException
     */
    public static function loadFromJson($jsonArr)
    {
        if (!is_array($jsonArr)) {
            throw new Dropbox_AppInfoLoadException("Expecting JSON object, got something else");
        }

        $requiredKeys = array("key", "secret");
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $jsonArr)) {
                throw new Dropbox_AppInfoLoadException("Missing field \"$key\"");
            }

            if (!is_string($jsonArr[$key])) {
                throw new Dropbox_AppInfoLoadException("Expecting field \"$key\" to be a string");
            }
        }

        // Check app_key and app_secret
        $appKey    = $jsonArr["key"];
        $appSecret = $jsonArr["secret"];

        $tokenErr = self::getTokenPartError($appKey);
        if (!is_null($tokenErr)) {
            throw new Dropbox_AppInfoLoadException("Field \"key\" doesn't look like a valid app key: $tokenErr");
        }

        $tokenErr = self::getTokenPartError($appSecret);
        if (!is_null($tokenErr)) {
            throw new Dropbox_AppInfoLoadException("Field \"secret\" doesn't look like a valid app secret: $tokenErr");
        }

        // Check for the optional 'host' field
        if (!array_key_exists('host', $jsonArr)) {
            $host = null;
        } else {
            $baseHost = $jsonArr["host"];
            if (!is_string($baseHost)) {
                throw new Dropbox_AppInfoLoadException("Optional field \"host\" must be a string");
            }

            $api     = "api-$baseHost";
            $content = "api-content-$baseHost";
            $web     = "meta-$baseHost";

            $host = new Dropbox_Host($api, $content, $web);
        }

        return new Dropbox_AppInfo($appKey, $appSecret, $host);
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

    /**
     * Use this to check that a function argument is either <code>null</code> or of type
     * <code>AppInfo</code>.
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

    /** @internal */
    public static function getTokenPartError($s)
    {
        if ($s === null) {
            return "can't be null";
        }
        if (strlen($s) === 0) {
            return "can't be empty";
        }
        if (strstr($s, ' ')) {
            return "can't contain a space";
        }

        return null;  // 'null' means "no error"
    }

    /** @internal */
    public static function checkKeyArg($key)
    {
        $error = self::getTokenPartError($key);
        if ($error === null) {
            return;
        }
        throw new InvalidArgumentException("Bad 'key': \"$key\": $error.");
    }

    /** @internal */
    public static function checkSecretArg($secret)
    {
        $error = self::getTokenPartError($secret);
        if ($error === null) {
            return;
        }
        throw new InvalidArgumentException("Bad 'secret': \"$secret\": $error.");
    }
}
