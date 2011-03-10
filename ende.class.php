<?php
/*
Ende - Simple text encryption and decryption
by Johan De Klerk
johan@wisi.co.za
*/

class Mmb_EnDe {

    var $key;
    var $data;

    var $td;
    var $iv;
    var $init = false;
    var $mcrypt_available = true;
    function __construct($key='',$data='') {
        if ($key != '') {
            $this->init($key);
        }
        $this->data = $data;
    }

    function init(&$key) {
        $this->key = substr (md5($key), 0, 16);
        if(function_exists('mcrypt_module_open')){
            //rijndael 128 and AES are similar except for key lengths
            $this->td = mcrypt_module_open (MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_ECB, '');
    //        $this->key = substr (md5($key), 0, mcrypt_enc_get_key_size ($this->td));
            //we will use 16 as key length to simulate AES and mysql aes_encrypt, aes_decrypt
            $iv_size = mcrypt_enc_get_iv_size ($this->td);
            $this->iv = mcrypt_create_iv ($iv_size, MCRYPT_RAND);
        }else{
            $this->mcrypt_available = false;
        }
            $this->init = true;
    }

    function setKey($key) {
        $this->init($key);
    }

    function setData($data) {
        $this->data = $data;
    }

    function & getKey() {
        return $this->key;
    }

    function & getData() {
        return $this->data;
    }

    function & encrypt($data='') {
        if($this->mcrypt_available)
            return $this->_crypt('encrypt',$data);
        else
            return $this->_mysql_crypt('encrypt', $data);
    }

    function & decrypt($data='') {
        if($this->mcrypt_available)
            return $this->_crypt('decrypt',$data);
        else
            return $this->_mysql_crypt('decrypt', $data);
    }

    function close() {
        mcrypt_module_close($this->td);
    }

    function & _crypt($mode,&$data) {
        if ($data != '') {
            $this->data = $data;
        }

        if ($this->init) {
            $ret = mcrypt_generic_init($this->td,$this->key,$this->iv);
            if ( ($ret >= 0) || ($ret !== false) ) {
                if ($mode == 'encrypt') {
                    $this->data = mcrypt_generic($this->td, $this->data);
                }
                elseif ($mode == 'decrypt') {
                    $this->data = mdecrypt_generic($this->td, $this->data);
                }

                mcrypt_generic_deinit($this->td);

                return $this->data;
            }
            else {
                trigger_error('Error initialising '.$mode.'ion handle',E_USER_ERROR);
            }
        }
        else {
            trigger_error('Key not set. Use setKey() method',E_USER_ERROR);
        }
    }

    function _mysql_crypt($mode, $data) {
        global $wpdb;
        
        if ($data != '') {
            $this->data = $data;
        }

        if($this->init){
            switch ($mode) {
                case 'encrypt':
                    return $wpdb->get_var("SELECT AES_ENCRYPT('{$data}', '{$this->key}')");
                    break;

                case 'encrypt':
                    return $wpdb->get_var("SELECT AES_DECRYPT('{$data}', '{$this->key}')");
                    break;

                default:
                    break;
            }
        }
    }
}
?>