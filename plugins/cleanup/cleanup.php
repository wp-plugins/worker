<?php
/*************************************************************
 * 
 * 
 * 
 * ManageWP Worker Plugin
 * 
 * 
 * Copyright (c) 2011 Prelovac Media
 * www.prelovac.com
 **************************************************************/

 add_filter('mmb_stats_filter', mmb_get_extended_info);
 
 
 function mmb_get_extended_info($stats)
 {
 	$stats['num_revisions'] = mmb_num_revisions();
 	$stats['overhead'] = mmb_get_overhead();
 	$stats['num_spam_comments'] = mmb_num_spam_comments();
 	return $stats;
 }
 
 /* Revisions */

mmb_add_action('cleanup_delete', 'cleanup_delete_worker');
 
function cleanup_delete_worker($params = array()){
	global $mmb_core;

	$params_array = explode('_', $params['actions']);
	$return_array = array();
	foreach ($params_array as $param){
			switch ($param){
				case 'revision' : 
					if(mmb_delete_all_revisions()){
						$return_array['revision'] = 'Revisions deleted.';
					}else{
						$return_array['revision'] = 'Revisions not deleted.';
					}
					break;
				case 'overhead' : 
					if(mmb_clear_overhead()){
						$return_array['overhead'] = 'Overhead cleared.';
					}else{
						$return_array['overhead'] = 'Overhead not cleared.';
					}
					break;
				case 'comment' : 
					if(mmb_delete_spam_comments()){
						$return_array['comment'] = 'Comments deleted';
					}else{
						$return_array['comment'] = 'Comments not deleted';
					}
					break;
				default: 
					break;
			}

		}
	
	unset($params);

	mmb_response($return_array, true);
}
 
 function mmb_num_revisions() {
    global $wpdb;
    $sql = "SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = 'revision'";
    $num_revisions = $wpdb->get_var($wpdb->prepare($sql));
    return $num_revisions;
}

 function mmb_select_all_revisions() {
    global $wpdb;
    $sql = "SELECT * FROM $wpdb->posts WHERE post_type = 'revision'";
    $revisions = $wpdb->get_results($wpdb->prepare($sql));
    return $revisions;
}

 function mmb_delete_all_revisions() {
    global $wpdb;
    $sql = "DELETE a,b,c FROM $wpdb->posts a LEFT JOIN $wpdb->term_relationships b ON (a.ID = b.object_id) LEFT JOIN $wpdb->postmeta c ON (a.ID = c.post_id) WHERE a.post_type = 'revision'";
    $revisions = $wpdb->query($wpdb->prepare($sql));
    return $revisions;
}



/* Optimize */

function mmb_get_overhead()
{
	global $wpdb, $mmb_core;
	$tot_data = 0;
	$tot_idx = 0;
	$tot_all = 0;
	$query = 'SHOW TABLE STATUS FROM '. DB_NAME;
	$tables = $wpdb->get_results($wpdb->prepare($query),ARRAY_A);
	foreach($tables as $table)
	{
		if($wpdb->base_prefix != $wpdb->prefix){
			if(preg_match('/^'.$wpdb->prefix.'*/Ui', $table['Name'])){
				$tot_data = $table['Data_length'];
				$tot_idx  = $table['Index_length'];
				$total = $tot_data + $tot_idx;
				$total = $total / 1024 ;
				$total = round ($total,3);
				$gain= $table['Data_free'];
				$gain = $gain / 1024 ;
				$total_gain += $gain;
				$gain = round ($gain,3);
			}
		} else if(preg_match('/^'.$wpdb->prefix.'[0-9]{1,20}_*/Ui', $table['Name'])){
			continue;
		}
		else {
			$tot_data = $table['Data_length'];
			$tot_idx  = $table['Index_length'];
			$total = $tot_data + $tot_idx;
			$total = $total / 1024 ;
			$total = round ($total,3);
			$gain= $table['Data_free'];
			$gain = $gain / 1024 ;
			$total_gain += $gain;
			$gain = round ($gain,3);
		}
	}
	return round($total_gain,3);
}


function mmb_clear_overhead()
{
        global $wpdb;
        $tables = $wpdb->get_col("SHOW TABLES");
        foreach ($tables as $table_name) {
			if($wpdb->base_prefix != $wpdb->prefix){
				if(preg_match('/^'.$wpdb->prefix.'*/Ui', $table_name)){
					$table_string .= $table_name . ",";
				}
			} else if(preg_match('/^'.$wpdb->prefix.'[0-9]{1,20}_*/Ui', $table_name)){
				continue;
			}
			else 
				$table_string .= $table_name . ",";
        }
        $table_string = substr($table_string,0,strlen($table_string)-1); //remove last ,

        $table_string = rtrim($table_string);
        
        $query = "OPTIMIZE TABLE $table_string";
        
        $optimize     = $wpdb->query($query);
        return $optimize ? true : false;
}




/* Spam Comments */

function mmb_num_spam_comments()
{
	global $wpdb;
	$sql = "SELECT COUNT(*) FROM $wpdb->comments WHERE comment_approved = 'spam'";
    $num_spams =  $wpdb->get_var($wpdb->prepare($sql));
    return $num_spams;
}

function mmb_delete_spam_comments()
{
	global $wpdb;
	$sql = "DELETE  FROM $wpdb->comments WHERE comment_approved = 'spam'";
    $spams = $wpdb->query($wpdb->prepare($sql));	
    return $sql;
}


 function mmb_get_spam_comments() {
    global $wpdb;
    $sql = "SELECT * FROM $wpdb->comments as a LEFT JOIN $wpdb->commentmeta as b WHERE a.comment_ID = b.comment_id AND a.comment_approved = 'spam'";
    $spams = $wpdb->get_results($wpdb->prepare($sql));
    return $spams;
}
?>