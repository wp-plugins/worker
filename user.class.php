<?php

class MMB_User extends MMB_Core
{
    function __construct()
    {
        parent::__construct();
    }
    
    /*************************************************************
    * FACADE functions
    * (functions to be called after a remote XMLRPC from Master)
    **************************************************************/    
	function change_password($params)
    {
        $this->_escape($params);
        $username = $params[0];
        $password = trim($params[1]);
        $new_password = trim(base64_decode($params[2]));
        
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

