<?php
  
class MMB_Theme extends MMB_Core
{
    function __construct()
    {
        parent::__construct();
    }
    
    
	function upload_theme_by_url($args){
		
		$this->_escape($args);
        $url = $args['url'];
		
		//return (print_r($args, true));
		ob_start();
			include_once(ABSPATH . 'wp-admin/includes/file.php');
			include_once(ABSPATH . 'wp-admin/includes/theme.php');
			include_once(ABSPATH . 'wp-admin/includes/misc.php');
			include_once(ABSPATH . 'wp-admin/includes/template.php');
			include_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');
		$upgrader = new Theme_Upgrader(); 
		$result = $upgrader->install($url);

		ob_end_clean();

		if(is_wp_error($upgrader->skin->result) || !$upgrader->skin->result){
			$error =  'Failed to upload theme. Check your URL.' ;
			return  array('bool' => false, 'message' => $error) ;
			
		}else {
			$theme = $upgrader->theme_info();
			return array('bool' => true, 'message' => 'Theme '.$upgrader->result[destination_name].' successfully installed ');
			
		}
		
	}

	function upgrade_all($params){
		
		$upgradable_themes  = $this->_get_upgradable_themes();
		
		$ready_for_upgrade = array();
		if(!empty($upgradable_themes)){
			foreach($upgradable_themes as $upgrade ){
				$ready_for_upgrade[] = $upgrade['theme_tmp'];
			}
		}
		if(!empty($ready_for_upgrade)){
			include_once(ABSPATH . 'wp-admin/includes/file.php');
			include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			$upgrader = new Theme_Upgrader(new Bulk_Theme_Upgrader_Skin( compact('title', 'nonce', 'url', 'theme') ));
			
			$result = $upgrader->bulk_upgrade($ready_for_upgrade);
			
			$results = array();
			if(!empty($result)){
				foreach($result as $theme_tmp => $info){
					if(is_wp_error($info) || !$info){
						$results[$theme_tmp] = '<code title="Please upgarde manualy">'.$theme_tmp.'</code> was not upgraded.';
					}
					else {
						$results[$theme_tmp] = '<code>'.$theme_tmp.'</code> succesfully upgraded.';
					}
				}
				return array('upgraded' => implode('', $results));
			}
			else return array('error' => 'Could not initialize upgrader.');
			
		}else {
			return array('error' => 'No themes available for upgrade.');
		}
	}
	
	function _get_upgradable_themes(){
        
		$all_themes = get_themes();
        $upgrade_themes = array();
		
		$current = $this->mmb_get_transient('update_themes');
		foreach ((array)$all_themes as $theme_template => $theme_data){
			if(!empty($current->response)){
				foreach ($current->response as $current_themes => $theme){
					if ($theme_data['Template'] == $current_themes)
					{
						$current->response[$current_themes]['name'] = $theme_data['Name'];
						$current->response[$current_themes]['old_version'] = $theme_data['Version'];
						$current->response[$current_themes]['theme_tmp'] = $theme_data['Template'];
						$upgrade_themes[] = $current->response[$current_themes];
						continue;
					}
				}
			} 
        }
        
        return $upgrade_themes;
    }
}
