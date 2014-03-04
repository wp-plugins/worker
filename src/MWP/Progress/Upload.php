<?php

class MWP_Progress_Upload extends MWP_Progress_Abstract
{
    /**
     * @var int
     */
    private $fileSize;

    /**
     * @var Monolog_Psr_LoggerInterface
     */
    private $logger;

    /**
     * @var int
     */
    private $lastProgress = 0;

    public function __construct($fileSize, $threshold, Monolog_Psr_LoggerInterface $logger)
    {
        $this->fileSize = $fileSize;
        $this->setThreshold($threshold);
        $this->logger = $logger;
    }

    public function callback(&$curl, $downloadSize, $downloadedSize, $uploadSize, $uploadedSize)
    {
        if (!$this->yieldCallback()) {
            return;
        }

        $offset             = $this->calculateOffset($curl);
        $currentProgress    = $uploadedSize + $offset;
        $speed              = $this->formatBytes(($currentProgress - $this->lastProgress) / $this->getThreshold());
        $this->lastProgress = $currentProgress;

        $progress = round($currentProgress / $this->fileSize * 100, 2);

        $this->logger->info(
          'Upload progress: {progress}% (speed: {speed}/s)',
          array(
            'progress' => $progress,
            'speed'    => $speed,
          )
        );
    }
}
