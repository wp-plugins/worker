<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Stream_ProcessOutput extends MWP_Stream_Callable
{

    /**
     * @var Symfony_Process_Process
     */
    private $process;

    /**
     * @var bool
     */
    private $ran = false;

    public function __construct(Symfony_Process_Process $process)
    {
        parent::__construct(array($this, 'getIncrementalOutput'));
        $this->process = $process;
    }

    public function getIncrementalOutput()
    {
        if (!$this->ran) {
            $this->ran = true;
            try {
                $this->process->start();
            } catch (Symfony_Process_Exception_ExceptionInterface $e) {
                throw new Symfony_Process_Exception_ProcessFailedException($this->process);
            }
        }

        if (!$this->process->isRunning() && !$this->process->isSuccessful()) {
            throw new Symfony_Process_Exception_ProcessFailedException($this->process);
        }

        $output = $this->process->getIncrementalOutput();
        if (!$this->process->isRunning() && empty($output)) {
            return false;
        }

        return empty($output) ? '' : $output;
    }
}
