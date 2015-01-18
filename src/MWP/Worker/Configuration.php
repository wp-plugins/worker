<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Worker_Configuration
{

    private $context;

    public function __construct(MWP_WordPress_Context $context)
    {
        $this->context = $context;
    }

    public function getPublicKey()
    {
        return base64_decode($this->context->optionGet('_worker_public_key'));
    }

    public function setPublicKey($publicKey)
    {
        $this->context->optionSet('_worker_public_key', base64_encode($publicKey));
    }

    public function deletePublicKey()
    {
        $this->context->optionDelete('_worker_public_key');
    }

    /**
     * @return string
     *
     * @deprecated Use public key instead.
     */
    public function getSecureKey()
    {
        return base64_decode($this->context->optionGet('_worker_nossl_key'));
    }

    /**
     * @param $secureKey
     *
     * @deprecated Use public key instead.
     */
    public function setSecureKey($secureKey)
    {
        $this->context->optionSet('_worker_nossl_key', base64_encode($secureKey));
    }

    public function deleteSecureKey()
    {
        $this->context->optionDelete('_worker_nossl_key');
    }
}
