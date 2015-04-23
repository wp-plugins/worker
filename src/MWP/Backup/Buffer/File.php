<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Backup_Buffer_File
{
    protected $filePointer;
    protected $fileName;
    protected $buffer = '';
    protected $bufferLimit;

    public function __construct($filename = null, $mode = 'w')
    {
        if (is_string($filename)) {
            $this->open($filename, $mode);
        }
    }

    public function open($filename, $mode = 'w')
    {
        $this->close();
        $this->filePointer = fopen($filename, $mode);

        return $this;
    }

    public function close()
    {
        if (is_resource($this->filePointer)) {
            fclose($this->filePointer);
        }

        return $this;
    }

    public function write($content)
    {
        $bufferLen  = strlen($this->buffer);
        $contentLen = strlen($content);
        // Check if buffer would be filled if we added the new content
        if ($bufferLen + $contentLen > $this->bufferLimit) {
            // Ok, check how much can we add to the existing buffer before flushing
            $spaceInBuffer = $this->bufferLimit - $bufferLen;
            // Append to the buffer
            $this->buffer .= substr($content, 0, $spaceInBuffer);
            // Take the remaining content
            $content = substr($content, $spaceInBuffer);

            $this->buffer .= "\n";
            $this->flushBuffer();

            // If there's more in the content var, repeat write
            if (strlen($content)) {
                $this->write($content);
            }
        } else {
            $this->buffer .= $content;
        }
    }

    public function flushBuffer()
    {
        fwrite($this->filePointer, $this->buffer);
        $this->buffer = '';

        return $this;
    }

    /**
     * @return int
     */
    public function getBufferLimit()
    {
        return $this->bufferLimit;
    }

    /**
     * @param int $bufferLimit
     */
    public function setBufferLimit($bufferLimit)
    {
        $this->bufferLimit = (int) $bufferLimit;

        return $this;
    }
}
