<?php

class MWP_Debug_ErrorCatcher
{
    private $errorMessage;

    private $registered;

    public function handleError($code, $message, $file = '', $line = 0, $context = array())
    {
        if (is_string($this->registered) && !($message = preg_replace('{^'.$this->registered.'\(.*?\): }', '', $message))) {
            return;
        }

        $this->errorMessage = $message;
    }

    public function getErrorMessage()
    {
        return $this->errorMessage;
    }

    public function yieldErrorMessage()
    {
        $message            = $this->errorMessage;
        $this->errorMessage = null;

        return $message;
    }

    public function register($capture = true)
    {
        if ($this->registered) {
            throw new LogicException('The error catcher is already registered.');
        }

        if ($capture !== true && (!is_string($capture) || empty($capture))) {
            throw new InvalidArgumentException('The "capture" must be boolean true or a non-empty string.');
        }

        $this->registered   = $capture;
        $this->errorMessage = null;
        set_error_handler(array($this, 'handleError'));
    }

    public function unRegister()
    {
        if (!$this->registered) {
            throw new LogicException('The error catcher is not registered.');
        }

        $this->registered = false;
        restore_error_handler();
    }

    public function __destruct()
    {
        if ($this->registered) {
            $this->unRegister();
        }
    }
}
