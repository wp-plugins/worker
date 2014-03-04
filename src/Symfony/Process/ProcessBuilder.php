<?php

class Symfony_Process_ProcessBuilder
{
    private $arguments;
    private $cwd;
    private $env = array();
    private $stdin;
    private $timeout = 60;
    private $options = array();
    private $inheritEnv = true;
    private $prefix = array();

    public function __construct(array $arguments = array())
    {
        $this->arguments = $arguments;
    }

    public static function create(array $arguments = array())
    {
        return new self($arguments);
    }

    /**
     * Adds an unescaped argument to the command string.
     *
     * @param string $argument A command argument
     *
     * @return Symfony_Process_ProcessBuilder
     */
    public function add($argument)
    {
        $this->arguments[] = $argument;

        return $this;
    }

    /**
     * Adds an unescaped prefix to the command string.
     *
     * The prefix is preserved when resetting arguments.
     *
     * @param string|array $prefix A command prefix or an array of command prefixes
     *
     * @return Symfony_Process_ProcessBuilder
     */
    public function setPrefix($prefix)
    {
        $this->prefix = is_array($prefix) ? $prefix : array($prefix);

        return $this;
    }

    /**
     * @param array $arguments
     *
     * @return Symfony_Process_ProcessBuilder
     */
    public function setArguments(array $arguments)
    {
        $this->arguments = $arguments;

        return $this;
    }

    public function setWorkingDirectory($cwd)
    {
        $this->cwd = $cwd;

        return $this;
    }

    public function inheritEnvironmentVariables($inheritEnv = true)
    {
        $this->inheritEnv = $inheritEnv;

        return $this;
    }

    public function setEnv($name, $value)
    {
        $this->env[$name] = $value;

        return $this;
    }

    public function addEnvironmentVariables(array $variables)
    {
        $this->env = Symfony_Process_ProcessUtils::arrayReplace($this->env, $variables);

        return $this;
    }

    public function setInput($stdin)
    {
        $this->stdin = $stdin;

        return $this;
    }

    /**
     * Sets the process timeout.
     *
     * To disable the timeout, set this value to null.
     *
     * @param float|null
     *
     * @return Symfony_Process_ProcessBuilder
     *
     * @throws Symfony_Process_Exception_InvalidArgumentException
     */
    public function setTimeout($timeout)
    {
        if (null === $timeout) {
            $this->timeout = null;

            return $this;
        }

        $timeout = (float) $timeout;

        if ($timeout < 0) {
            throw new Symfony_Process_Exception_InvalidArgumentException('The timeout value must be a valid positive integer or float number.');
        }

        $this->timeout = $timeout;

        return $this;
    }

    public function setOption($name, $value)
    {
        $this->options[$name] = $value;

        return $this;
    }

    public function getProcess()
    {
        if (0 === count($this->prefix) && 0 === count($this->arguments)) {
            throw new Symfony_Process_Exception_LogicException('You must add() command arguments before calling getProcess().');
        }

        $options = $this->options;

        $arguments = array_merge($this->prefix, $this->arguments);
        $script    = implode(' ', array_map(array('Symfony_Process_ProcessUtils', 'escapeArgument'), $arguments));

        if ($this->inheritEnv) {
            // include $_ENV for BC purposes
            $env = Symfony_Process_ProcessUtils::arrayReplace($_ENV, $_SERVER, $this->env);
        } else {
            $env = $this->env;
        }

        return new Symfony_Process_Process($script, $this->cwd, $env, $this->stdin, $this->timeout, $options);
    }

    /**
     * Escapes a string to be used as a shell argument.
     *
     * @param string $argument The argument that will be escaped
     *
     * @return string The escaped argument
     */
    public static function escapeArgument($argument)
    {
        //Fix for PHP bug #43784 escapeshellarg removes % from given string
        //Fix for PHP bug #49446 escapeshellarg dosn`t work on windows
        //@see https://bugs.php.net/bug.php?id=43784
        //@see https://bugs.php.net/bug.php?id=49446
        if (Symfony_Process_ProcessUtils::isWindows()) {
            if ('' === $argument) {
                return escapeshellarg($argument);
            }

            $escapedArgument = '';
            foreach (preg_split('/([%"])/i', $argument, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE) as $part) {
                if ('"' === $part) {
                    $escapedArgument .= '\\"';
                } elseif ('%' === $part) {
                    $escapedArgument .= '^%';
                } else {
                    $escapedArgument .= escapeshellarg($part);
                }
            }

            return $escapedArgument;
        }

        return escapeshellarg($argument);
    }
}
