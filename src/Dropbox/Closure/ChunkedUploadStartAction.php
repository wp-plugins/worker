<?php

class Dropbox_Closure_ChunkedUploadStartAction implements Dropbox_Closure_ReRunnableActionInterface
{
    private $client;

    private $data;

    private $callback;

    public function __construct(Dropbox_Client $client, $data, $callback = null)
    {
        $this->client   = $client;
        $this->data     = $data;
        $this->callback = $callback;
    }

    public function run()
    {
        return $this->client->chunkedUploadStart($this->data, $this->callback);
    }
}
