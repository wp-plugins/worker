<?php

class Dropbox_Closure_ChunkedUploadContinueAction implements Dropbox_Closure_ReRunnableActionInterface
{
    private $client;

    private $uploadId;

    private $byteOffset;

    private $data;

    private $callback;

    public function __construct(Dropbox_Client $client, $uploadId, $byteOffset, $data, $callback = null)
    {
        $this->client     = $client;
        $this->uploadId   = $uploadId;
        $this->byteOffset = $byteOffset;
        $this->data       = $data;
        $this->callback   = $callback;
    }

    public function run()
    {
        return $this->client->chunkedUploadContinue($this->uploadId, $this->byteOffset, $this->data, $this->callback);
    }
}
