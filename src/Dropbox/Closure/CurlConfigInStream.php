<?php

class Dropbox_Closure_CurlConfigInStream implements Dropbox_Closure_CurlConfigInterface
{
    private $inStream;

    private $numBytes;

    private $callback;

    public function __construct($inStream, $numBytes, $callback = null)
    {
        $this->inStream = $inStream;
        $this->numBytes = $numBytes;
        $this->callback = $callback;
    }

    public function configure(Dropbox_Curl $curl)
    {
        $curl->set(CURLOPT_PUT, true);
        $curl->set(CURLOPT_INFILE, $this->inStream);
        $curl->set(CURLOPT_INFILESIZE, $this->numBytes);
    }
}
