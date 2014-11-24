<?php

class Dropbox_Closure_ChunkedUploadFinishAction implements Dropbox_Closure_ReRunnableActionInterface
{
    private $client;

    private $uploadId;

    private $path;

    private $writeMode;

    public function __construct(Dropbox_Client $client, $uploadId, $path, $writeMode)
    {
        $this->client    = $client;
        $this->uploadId  = $uploadId;
        $this->path      = $path;
        $this->writeMode = $writeMode;
    }

    public function run()
    {
        return $this->client->chunkedUploadFinish($this->uploadId, $this->path, $this->writeMode);
    }
}
