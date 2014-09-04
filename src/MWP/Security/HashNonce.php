<?php

/**
 * Created by miljenko.rebernisak@prelovac.com
 * Date: 2/12/14
 */
class MWP_Security_HashNonce implements MWP_Security_NonceInterface
{
    /**
     * How much is this nonce valid for use
     */
    const NONCE_LIFETIME = 43200;
    /**
     * Blacklist time of nonce. The minimum value is NONCE_LIFETIME +1
     */
    const NONCE_BLACKLIST_TIME = 86400;
    /**
     * @var string
     */
    protected $nonce;
    /**
     * @var int
     */
    protected $issueAt;

    /**
     * {@inherits}
     */
    public function setValue($value)
    {
        $parts = explode("_", $value);
        if (count($parts) == 2) {
            list($this->nonce, $this->issueAt) = $parts;
        }
    }

    /**
     * {@inherits}
     */
    public function verify()
    {
        if (empty($this->nonce) || (int) $this->issueAt == 0) {
            return false;
        }
        if ($this->issueAt + self::NONCE_LIFETIME < time()) {
            return false;
        }
        $nonceUsed = get_transient('n_'.$this->nonce);

        if ($nonceUsed !== false) {
            return false;
        }
        set_transient('n_'.$this->nonce, $this->issueAt, self::NONCE_BLACKLIST_TIME); //need shorter name, because of 64 char limit

        return true;
    }
} 
