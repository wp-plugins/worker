<?php

/**
 * Thrown by the <code>AuthInfo::loadXXX</code> methods if something goes wrong.
 */
final class Dropbox_AuthInfoLoadException extends Dropbox_Exception
{
    /**
     * @param string $message
     *
     * @internal
     */
    function __construct($message)
    {
        parent::__construct($message);
    }
}
