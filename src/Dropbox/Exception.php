<?php

/**
 * The base class for all API call exceptions.
 */
class Dropbox_Exception extends Exception
{
    public $previousException;

    /**
     * @internal
     */
    public function __construct($message, $cause = null)
    {
        if (version_compare(PHP_VERSION, '5.3', '<')) {
            $this->previousException = $cause;
            parent::__construct($message, 0);

            return;
        }
        parent::__construct($message, 0, $cause);
    }

    /**
     * @return mixed
     */
    public function getPreviousException()
    {
        return $this->previousException;
    }
}
