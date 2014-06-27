<?php

interface MWP_Backup_Writer_WriterInterface
{
    public function open();

    public function write($content = '');

    public function close();

    public function writeLine($content = '');

    public function consoleOutput($outputCommand, $destination, $options = null);

}