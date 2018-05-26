<?php

class qa_html_theme_layer extends qa_html_theme_base {
	function doctype(){
		qa_html_theme_base::doctype();
		// set up sub navigation for admin page
		if(qa_get_logged_in_level() >= QA_USER_LEVEL_ADMIN)	{
			if ($this->request == 'admin/users_management' or $this->request == 'admin/user_register') {
				require_once QA_INCLUDE_DIR.'qa-app-admin.php';
				$admin_nav = qa_admin_sub_navigation();
				$this->content['navigation']['sub'] = array_merge(
					$admin_nav,
					$this->content['navigation']['sub']
				);
			}
			if ( ($this->template=='admin') or ($this->request == 'users_management') ){
				$this->content['navigation']['sub']['users_management'] = array(
					'label' => 'User Manager',
					'url' => qa_path_html('admin/users_management', array('page' => 1)),
				);
				if ($this->request == 'admin/users_management'){
					$this->content['navigation']['sub']['users_management']['selected'] = true;
				}
			}
		}
	}

	var $plugin_directory;
	var $plugin_url;
	function qa_html_theme_layer($template, $content, $rooturl, $request)
	{
		global $qa_layers;
		$this->plugin_directory = $qa_layers['um Layer']['directory'];
		$this->plugin_url = $qa_layers['um Layer']['urltoroot'];
		qa_html_theme_base::qa_html_theme_base($template, $content, $rooturl, $request);
	}
	function head_css() {
		global $qa_request;
		if ( ($qa_request == 'admin/users_management') && (qa_get_logged_in_level()>=QA_USER_LEVEL_ADMIN) )
			$this->output('<LINK REL="stylesheet" TYPE="text/css" HREF="'. qa_opt('site_url') . $this->plugin_url.'include/styles.css'.'"/>');
		qa_html_theme_base::head_css();
	}	
	function head_script(){
		qa_html_theme_base::head_script();
		global $qa_request;
		if ( ($qa_request == 'admin/users_management') && (qa_get_logged_in_level()>=QA_USER_LEVEL_ADMIN) ){
			$this->output('<script type="text/javascript" src="'. qa_opt('site_url') . $this->plugin_url .'include/mains.js"></script>');  
		}
	}	

}
