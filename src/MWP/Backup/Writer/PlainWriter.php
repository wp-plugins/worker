<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Backup_Writer_PlainWriter implements MWP_Backup_Writer_WriterInterface
{
    protected $file;
    protected $filename;
    protected $mode;

    public function __construct($filename, $mode = 'w')
    {
        $this->setFilename($filename);
        $this->setMode($mode);
    }

    public function open()
    {
        $this->file = fopen($this->getFilename(), $this->getMode());
        if ($this->file === false) {
            throw new MWP_Backup_Exception("Could not open file: $this->getFilename()");
        }

        return $this;
    }

    /**
     * @return mixed
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * @param mixed $filename
     */
    public function setFilename($filename)
    {
        $this->filename = $filename;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getMode()
    {
        return $this->mode;
    }

    /**
     * @param mixed $mode
     */
    public function setMode($mode)
    {
        $this->mode = $mode;
        if (is_resource($this->file)) {
            $this->close();
            $this->open();
        }

        return $this;
    }

    public function write($content = '')
    {
        fwrite($this->getFile(), $content);
    }

    /**
     * @return mixed
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * @param mixed $file
     */
    public function setFile($file)
    {
        $this->file = $file;

        return $this;
    }

    public function writeLine($content = '')
    {
        fwrite($this->getFile(), $content."\n");
    }

    public function close()
    {
        @fclose($this->getFile());

        return $this;
    }

    public function consoleOutput($outputCommand, $destination, $options = null)
    {
        return "$outputCommand > $destination";
    }
}
