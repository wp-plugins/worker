<?php

class Mmb_Comment extends Mmb_Core
{
    function __construct()
    {
        parent::__construct();
    }

    function bulk_edit_comments($args) {
        
        $this->_escape($args);
        $username = $args[0];
        $password = $args[1];
        $comment_ids = $args[2];
        $status = $args[3];

        if ( !$user = $this->login($username, $password) )
                return $this->error;

        if ( !current_user_can( 'moderate_comments' ) )
                return new IXR_Error( 403, __( 'You are not allowed to moderate comments on this blogs.' ) );

        $flag = false;
        foreach($comment_ids as $comment_id){
            $commentarr = array( 'comment_ID' => $comment_id,
                                 'comment_approved' => $status
                               );
            $success = wp_update_comment($commentarr);
            if(!$success)
                $flag = true;
        }

        if($flag)
            return 2;
        else
            return 1;
    }

    function get_comment_count($args) {
        $this->_escape($args);
        $username	= $args[0];
        $password	= $args[1];

        if ( !$user = $this->login($username, $password) )
                return $this->error;

        if ( !current_user_can( 'edit_posts' ) )
                return new IXR_Error( 403, __( 'You are not allowed access to details about comments.' ) );

        $count = wp_count_comments();
        return array(
                "approved" => $count->approved,
                "awaiting_moderation" => $count->moderated,
                "spam" => $count->spam,
                "trash" => $count->trash,
//                "total_comments" => $count->total_comments + $count->trash
                "total_comments" => $count->total_comments
        );
    }

    function restore_comment($args) {
        $this->_escape($args);
        $username = $args[0];
        $password = $args[1];
        $comment_id = $args[2];
        
        if ( !$user = $this->login($username, $password) )
                return $this->error;

        if ( !current_user_can( 'moderate_comments' ) )
                return new IXR_Error( 403, __( 'You are not allowed to moderate comments on this blogs.' ) );

	$status = (string) get_comment_meta($comment_id, '_wp_trash_meta_status', true);
//        $this->_log($status);
        $success = wp_untrash_comment($comment_id);
//        $this->_log($success);

        if(!$success)
            return false;
        else
            return $status;

    }
}