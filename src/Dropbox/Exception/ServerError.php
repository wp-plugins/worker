<?php

/**
 * The Dropbox server said that there was an internal error when trying to fulfil our request.
 * This usually corresponds to an HTTP 500 response.
 */
final class Dropbox_Exception_ServerError extends Dropbox_Exception
{
    /** @internal */
    public function __construct($message = "")
    {
        parent::__construct($message);
    }
}
