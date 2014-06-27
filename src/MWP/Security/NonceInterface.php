<?php

/**
 * Created by miljenko.rebernisak@prelovac.com
 * Date: 2/12/14
 */
interface MWP_Security_NonceInterface
{

    /**
     * Parse input string and sets inner fields
     *
     * @param string $value
     *
     * @return mixed
     */
    public function setValue($value);

    /**
     * Returns if nonce is valid
     *
     * @return bool
     */
    public function verify();
} 
