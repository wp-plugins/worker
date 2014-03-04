<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * ProcessPipes manages descriptors and pipes for the use of proc_open.
 */
class Symfony_Process_ProcessPipes
{
    /** @var array */
    public $pipes = array();

    /** @var array */
    private $fileHandles = array();

    /** @var array */
    private $readBytes = array();

    /** @var bool */
    private $useFiles;

    /** @var bool */
    private $ttyMode;

    public function __construct($useFiles, $ttyMode)
    {
        $this->useFiles = (bool) $useFiles;
        $this->ttyMode  = (bool) $ttyMode;

        // Fix for PHP bug #51800: reading from STDOUT pipe hangs forever on Windows if the output is too big.
        // Workaround for this problem is to use temporary files instead of pipes on Windows platform.
        //
        // Please note that this work around prevents hanging but
        // another issue occurs : In some race conditions, some data may be
        // lost or corrupted.
        //
        // @see https://bugs.php.net/bug.php?id=51800
        if ($this->useFiles) {
            $this->fileHandles = array(
              Symfony_Process_Process::STDOUT => tmpfile(),
            );

            if (false === $this->fileHandles[Symfony_Process_Process::STDOUT]) {
                throw new Symfony_Process_Exception_RuntimeException('A temporary file could not be opened to write the process output to, verify that your TEMP environment variable is writable');
            }
            $this->readBytes = array(
              Symfony_Process_Process::STDOUT => 0,
            );
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * Sets non-blocking mode on pipes.
     */
    public function unblock()
    {
        foreach ($this->pipes as $pipe) {
            stream_set_blocking($pipe, 0);
        }
    }

    /**
     * Closes file handles and pipes.
     */
    public function close()
    {
        $this->closeUnixPipes();
        foreach ($this->fileHandles as $handle) {
            fclose($handle);
        }
        $this->fileHandles = array();
    }

    /**
     * Closes unix pipes.
     *
     * Nothing happens in case file handles are used.
     */
    public function closeUnixPipes()
    {
        foreach ($this->pipes as $pipe) {
            fclose($pipe);
        }
        $this->pipes = array();
    }

    /**
     * Returns an array of descriptors for the use of proc_open.
     *
     * @return array
     */
    public function getDescriptors()
    {
        if ($this->useFiles) {
            return array(
              array('pipe', 'r'),
              $this->fileHandles[Symfony_Process_Process::STDOUT],
                // Use a file handle only for STDOUT. Using for both STDOUT and STDERR would trigger https://bugs.php.net/bug.php?id=65650
              array('pipe', 'w'),
            );
        }

        if ($this->ttyMode) {
            return array(
              array('file', '/dev/tty', 'r'),
              array('file', '/dev/tty', 'w'),
              array('file', '/dev/tty', 'w'),
            );
        }

        return array(
          array('pipe', 'r'), // stdin
          array('pipe', 'w'), // stdout
          array('pipe', 'w'), // stderr
        );
    }

    /**
     * Reads data in file handles and pipes.
     *
     * @param bool $blocking Whether to use blocking calls or not.
     *
     * @return array An array of read data indexed by their fd.
     */
    public function read($blocking)
    {
        return Symfony_Process_ProcessUtils::arrayReplace($this->readStreams($blocking), $this->readFileHandles());
    }

    /**
     * Reads data in file handles and pipes, closes them if EOF is reached.
     *
     * @param bool $blocking Whether to use blocking calls or not.
     *
     * @return array An array of read data indexed by their fd.
     */
    public function readAndCloseHandles($blocking)
    {
        $streams = $this->readStreams($blocking, true);
        $handles = $this->readFileHandles(true);

        return Symfony_Process_ProcessUtils::arrayReplace($streams, $handles);
    }

    /**
     * Returns if the current state has open file handles or pipes.
     *
     * @return bool
     */
    public function hasOpenHandles()
    {
        if (!$this->useFiles) {
            return (bool) $this->pipes;
        }

        return (bool) $this->pipes && (bool) $this->fileHandles;
    }

    /**
     * Writes stdin data.
     *
     * @param bool        $blocking Whether to use blocking calls or not.
     * @param string|null $stdin    The data to write.
     */
    public function write($blocking, $stdin)
    {
        if (null === $stdin) {
            fclose($this->pipes[0]);
            unset($this->pipes[0]);

            return;
        }

        $writePipes = array($this->pipes[0]);
        unset($this->pipes[0]);
        $stdinLen    = strlen($stdin);
        $stdinOffset = 0;

        while ($writePipes) {
            $r = null;
            $w = $writePipes;
            $e = null;

            if (false === $n = @stream_select($r, $w, $e, 0, $blocking ? ceil(Symfony_Process_Process::TIMEOUT_PRECISION * 1E6) : 0)) {
                // if a system call has been interrupted, forget about it, let's try again
                if ($this->hasSystemCallBeenInterrupted()) {
                    continue;
                }
                break;
            }

            // nothing has changed, let's wait until the process is ready
            if (0 === $n) {
                continue;
            }

            if ($w) {
                $written = fwrite($writePipes[0], (binary) substr($stdin, $stdinOffset), 8192);
                if (false !== $written) {
                    $stdinOffset += $written;
                }
                if ($stdinOffset >= $stdinLen) {
                    fclose($writePipes[0]);
                    $writePipes = null;
                }
            }
        }
    }

    /**
     * Reads data in file handles.
     *
     * @return array An array of read data indexed by their fd.
     */
    private function readFileHandles($close = false)
    {
        $read = array();
        $fh   = $this->fileHandles;
        foreach ($fh as $type => $fileHandle) {
            if (0 !== fseek($fileHandle, $this->readBytes[$type])) {
                continue;
            }
            $data     = '';
            $dataread = null;
            while (!feof($fileHandle)) {
                if (false !== $dataread = fread($fileHandle, 16392)) {
                    $data .= $dataread;
                }
            }
            if (0 < $length = strlen($data)) {
                $this->readBytes[$type] += $length;
                $read[$type] = $data;
            }

            if (false === $dataread || (true === $close && feof($fileHandle) && '' === $data)) {
                fclose($this->fileHandles[$type]);
                unset($this->fileHandles[$type]);
            }
        }

        return $read;
    }

    /**
     * Reads data in file pipes streams.
     *
     * @param bool $blocking Whether to use blocking calls or not.
     *
     * @return array An array of read data indexed by their fd.
     */
    private function readStreams($blocking, $close = false)
    {
        if (empty($this->pipes)) {
            return array();
        }

        $read = array();

        $r = $this->pipes;
        $w = null;
        $e = null;

        // let's have a look if something changed in streams
        if (false === $n = @stream_select($r, $w, $e, 0, $blocking ? ceil(Symfony_Process_Process::TIMEOUT_PRECISION * 1E6) : 0)) {
            // if a system call has been interrupted, forget about it, let's try again
            // otherwise, an error occurred, let's reset pipes
            if (!$this->hasSystemCallBeenInterrupted()) {
                $this->pipes = array();
            }

            return $read;
        }

        // nothing has changed
        if (0 === $n) {
            return $read;
        }

        foreach ($r as $pipe) {
            $type = array_search($pipe, $this->pipes);
            $data = fread($pipe, 8192);

            if (strlen($data) > 0) {
                $read[$type] = $data;
            }

            if (false === $data || (true === $close && feof($pipe) && '' === $data)) {
                fclose($this->pipes[$type]);
                unset($this->pipes[$type]);
            }
        }

        return $read;
    }

    /**
     * Returns true if a system call has been interrupted.
     *
     * @return bool
     */
    private function hasSystemCallBeenInterrupted()
    {
        $lastError = error_get_last();

        // stream_select returns false when the `select` system call is interrupted by an incoming signal
        return isset($lastError['message']) && false !== stripos($lastError['message'], 'interrupted system call');
    }
}
