<?php

class Dropbox_Closure_CurlConfigOctetStream implements Dropbox_Closure_CurlConfigInterface
{
    private $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function configure(Dropbox_Curl $curl)
    {
        $curl->set(CURLOPT_CUSTOMREQUEST, "PUT");
        $curl->set(CURLOPT_POSTFIELDS, $this->data);
        $curl->addHeader("Content-Type: application/octet-stream");
    }
}
