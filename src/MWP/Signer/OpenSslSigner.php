<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Signer_OpenSslSigner implements MWP_Signer_Interface
{
    public function sign($data, $publicKey)
    {
        $success = @openssl_sign($data, $signature, $publicKey);

        if ($success === false) {
            $error     = $errorRow = '';
            $lastError = error_get_last();

            while (($errorRow = openssl_error_string()) !== false) {
                $error = $errorRow."\n".$error;
            }

            throw new MWP_Worker_Exception(MWP_Worker_Exception::OPENSSL_SIGN_ERROR, "There was an error while trying to use OpenSSL to sign a message.", array(
                'openSslError' => $error,
                'error'        => isset($lastError['message']) ? $lastError['message'] : null,
            ));
        }

        return $signature;
    }

    public function verify($data, $signature, $publicKey)
    {
        $verify = @openssl_verify($data, $signature, $publicKey);

        if ($verify === -1) {
            $error     = $errorRow = '';
            $lastError = error_get_last();

            while (($errorRow = openssl_error_string()) !== false) {
                $error = $errorRow."\n".$error;
            }

            throw new MWP_Worker_Exception(MWP_Worker_Exception::OPENSSL_VERIFY_ERROR, "There was an error while trying to use OpenSSL to verify a message.", array(
                'openSslError' => $error,
                'error'        => isset($lastError['message']) ? $lastError['message'] : null,
            ));
        }

        return (bool) $verify;
    }
}