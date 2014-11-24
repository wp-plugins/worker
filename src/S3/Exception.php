<?php

/**
 * S3 exception class
 *
 * @link    http://undesigned.org.za/2007/10/22/amazon-s3-php-class
 * @version 0.5.0-dev
 */
class S3_Exception extends Exception
{
    /**
     * Class constructor
     *
     * @param string $message Exception message
     * @param string $file    File in which exception was created
     * @param string $line    Line number on which exception was created
     * @param int    $code    Exception code
     */
    public function __construct($message, $file, $line, $code = 0)
    {
        parent::__construct($message, $code);
        $this->file = $file;
        $this->line = $line;
    }
}
