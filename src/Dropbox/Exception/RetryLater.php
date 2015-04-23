<?php

/**
 * The Dropbox server said it couldn't fulfil our request right now, but that we should try
 * again later.
 */
final class Dropbox_Exception_RetryLater extends Dropbox_Exception
{
    /**
     * @internal
     */
    public function __construct($message)
    {
        parent::__construct($message);
    }
}
