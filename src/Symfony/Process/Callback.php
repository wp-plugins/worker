<?php

class Symfony_Process_Callback
{
    private $process;

    private $callback;

    private $out;

    private $err;

    public function __construct(Symfony_Process_Process $process, $callback, $out, $err)
    {
        $this->process  = $process;
        $this->callback = $callback;
        $this->out      = $out;
        $this->err      = $err;
    }

    public function callback($type, $data)
    {
        if ($this->out === $type) {
            $this->process->addOutput($data);
        } else {
            $this->process->addErrorOutput($data);
        }

        if (null !== $this->callback) {
            call_user_func($this->callback, $type, $data);
        }
    }
}
