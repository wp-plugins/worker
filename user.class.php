<?php

class Mmb_User extends Mmb_Core
{
    function __construct()
    {
        parent::__construct();
    }
    
    /*************************************************************
    * FACADE functions
    * (functions to be called after a remote XMLRPC from Master)
    **************************************************************/    
 function change_password($args)
    {
        $this->_escape($args);
        $username = $args[0];
        $password = trim($args[1]);
        $new_password = trim(base64_decode($args[2]));
        
        if ((!$user = $this->login($username, $password)) || ($new_password ==''))
        {
            return FALSE;
        }

        wp_update_user(array(
            'ID'            => $user->data->ID,
            'user_pass'     => $new_password,
        ));

        return TRUE;
    }
}

