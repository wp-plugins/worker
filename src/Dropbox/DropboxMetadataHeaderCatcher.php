<?php

/**
 * @internal
 */
final class Dropbox_DropboxMetadataHeaderCatcher
{
    /**
     * @var mixed
     */
    public $metadata = null;

    /**
     * @var string
     */
    public $error = null;

    /**
     * @var bool
     */
    public $skippedFirstLine = false;

    /**
     * @param resource $ch
     */
    public function __construct($ch)
    {
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, array($this, 'headerFunction'));
    }

    /**
     * @param resource $ch
     * @param string   $header
     *
     * @return int
     * @throws Dropbox_Exception_BadResponse
     */
    public function headerFunction($ch, $header)
    {
        // The first line is the HTTP status line (Ex: "HTTP/1.1 200 OK").
        if (!$this->skippedFirstLine) {
            $this->skippedFirstLine = true;

            return strlen($header);
        }

        // If we've encountered an error on a previous callback, then there's nothing left to do.
        if ($this->error !== null) {
            return strlen($header);
        }

        // case-insensitive starts-with check.
        if (strlen($header) < 19 || substr_compare($header, "x-dropbox-metadata:", 0, 19, true) !== 0) {
            return strlen($header);
        }

        if ($this->metadata !== null) {
            $this->error = "Duplicate X-Dropbox-Metadata header";

            return strlen($header);
        }

        $headerValue = substr($header, 19);
        $parsed      = json_decode($headerValue, true);

        if ($parsed === null) {
            $this->error = "Bad JSON in X-Dropbox-Metadata header";

            return strlen($header);
        }

        $this->metadata = $parsed;

        return strlen($header);
    }

    public function getMetadata()
    {
        if ($this->error !== null) {
            throw new Dropbox_Exception_BadResponse($this->error);
        }
        if ($this->metadata === null) {
            throw new Dropbox_Exception_BadResponse("Missing X-Dropbox-Metadata header");
        }

        return $this->metadata;
    }
}
