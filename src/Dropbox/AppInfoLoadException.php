<?php

/**
 * Thrown by the <code>AppInfo::loadXXX</code> methods if something goes wrong.
 */
final class Dropbox_AppInfoLoadException extends Dropbox_Exception
{
    /**
     * @param string $message
     *
     * @internal
     */
    public function __construct($message)
    {
        parent::__construct($message);
    }
}
