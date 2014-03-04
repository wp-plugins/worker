<?php

interface MWP_Progress_CurlCallbackInterface
{
    public function callback(&$curl, $downloadSize, $downloadedSize, $uploadSize, $uploadedSize);

    public function getCallback();
}
