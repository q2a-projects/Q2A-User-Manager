<?php
/* don't allow this page to be requested directly from browser */	
if (!defined('QA_VERSION')) {
		header('Location: /');
		exit;
}
class admin_um {
	var $directory;
	var $urltoroot;
	var $added;
	
	function load_module($directory, $urltoroot) {
		$this->directory=$directory;
		$this->urltoroot=$urltoroot;
	}

	function match_request($request){
		if ($request=='admin/users_management' or $request=='admin/user_register' )
			return true;
		return false;
	}
	
	function process_request($request)
	{
		if (qa_get_logged_in_level() < QA_USER_LEVEL_ADMIN){
			$qa_content=qa_content_prepare();
			$qa_content['error']="You don't have permission to access this page.";
			return $qa_content;
		}
		if (QA_FINAL_EXTERNAL_USERS){
			$qa_content=qa_content_prepare();
			$qa_content['error']="User registration is handled by external code.";
			return $qa_content;
		}
		
		$qa_content=qa_content_prepare();
		if ($request=='admin/users_management'){
			$qa_content['site_title']="User Management";
			$qa_content['title']="User Management";
			$qa_content['custom'] = $this->page_form_user_management();
		}elseif ($request=='admin/user_register'){
			$qa_content['site_title']="Register Users";
			$qa_content['title']="Register User";
			$qa_content['custom'] = $this->page_form_user_register();
		}
		$qa_content['error']="";
		$qa_content['suggest_next']="";
		$qa_content['sidepanel'] = '';
		return $qa_content;	
	}
	
	function page_form_user_management(){
		$output = '';
		$messages = array();
		if (qa_clicked('do_action')){
			require_once QA_INCLUDE_DIR.'qa-db-users.php';
			require_once QA_INCLUDE_DIR.'qa-app-limits.php';
			require_once QA_INCLUDE_DIR.'qa-app-users-edit.php';
			
			$action = @$_POST['um-action'];
			$userids = @$_POST['chk-user-checked'];
			$n = count($userids);
			
			if($action=='blockuser'){
				for($i=0; $i < $n; $i++){
					qa_set_user_blocked($userids[$i],null, true);
				}
				$messages[] = $n . ' users were blocked.';
			}
			if($action=='removeuser'){
				// get posts from all selected users
				$posts = qa_db_read_all_assoc(qa_db_query_sub('SELECT postid FROM ^posts WHERE userid IN (#)',
					$userids)
				);
				// remove user posts
				foreach($posts as $post){
					um_post_delete_recursive($post['postid']);
				}
				$messages[] = count($posts) . ' posts were removed.';
				// remove User
				qa_db_query_sub('UPDATE ^posts SET lastuserid=NULL WHERE lastuserid IN (#)', $userids);
				qa_db_query_sub('DELETE FROM ^userpoints WHERE userid IN (#)', $userids);
				qa_db_query_sub('DELETE FROM ^blobs WHERE blobid in(SELECT avatarblobid FROM ^users WHERE userid IN (#))', $userids);
				qa_db_query_sub('DELETE FROM ^users WHERE userid IN (#)', $userids);
				qa_db_query_sub('UPDATE ^posts SET userid=NULL WHERE userid IN (#)', $userids);
				qa_db_query_sub('DELETE FROM ^userlogins WHERE userid IN (#)', $userids);
				qa_db_query_sub('DELETE FROM ^userprofile WHERE userid IN (#)', $userids);
				qa_db_query_sub('DELETE FROM ^userfavorites WHERE userid IN (#)', $userids);
				qa_db_query_sub('DELETE FROM ^userevents WHERE userid IN (#)', $userids);
				qa_db_query_sub('DELETE FROM ^uservotes WHERE userid IN (#)', $userids);
				qa_db_query_sub('DELETE FROM ^userlimits WHERE userid IN (#)', $userids);
				$messages[] = $n . ' users were removed.';
			}
			if($action=='sendconfirm'){
				for($i=0; $i < $n; $i++){
					qa_send_new_confirm($userids[$i]);
				}
				$messages[] = 'Confirmation message was sent to ' . $n . ' selected users';
			}
		}
		// Load limited users per page
		$user_per_page = (int)qa_html(qa_opt('um_users_per_page'));
		$current_page = (int)$_GET['page']-1;
		$users_count = qa_db_read_one_value(qa_db_query_sub('SELECT count(*) FROM ^users;'));
		if($user_per_page == 0) $number_of_pages = 1; else $number_of_pages = ceil($users_count / $user_per_page);
		if($user_per_page != 0 or $number_of_pages != 1){
			$limit_users = ' LIMIT ' . $current_page*$user_per_page . ',' . $user_per_page;
			$page_list = "";
			for ($i=1; $i < $number_of_pages+1; $i++) { 
				$page_list .= "<option value='" . $i . "' " . ($current_page == $i ? "selected" : "") . ">" . $i . "</option>";
			}
			$page_form = "
				<div style='display: inline-block; width: 99%; padding: 0px 1% 10px 0px;'>
					<form  method='GET'>
						Showing <b>" . $user_per_page . "</b> users in page
					    <select name='page' id='page' onchange='this.form.submit()'>
					    	" . $page_list . "
					    </select>
					    </br><small>You can set up number of loaded users in plugin's option. a normal computer and normal shared host can easily load up to 5000 users per page.</small>
						<hr style='color: #000;border-top: 1px solid;height: 0;'>
					</div>
				</form>

			";
		}else{
			$limit_users = '';
			$page_form = '';
		}
		// Load active or all users
		if( qa_opt('um_users_filter')=='active' ){
			$extra_fields = ', aselects, aselecteds, qupvotes, qdownvotes, aupvotes, adownvotes, qvoteds, avoteds, upvoteds, downvoteds';
			$load_filter = ' WHERE aselects <> 0 OR aselecteds <> 0 OR qupvotes <> 0 OR qdownvotes <> 0 OR aupvotes <> 0 OR adownvotes <> 0 OR qvoteds <> 0 OR avoteds <> 0 OR upvoteds <> 0 OR downvoteds <> 0';
		}elseif( qa_opt('um_users_filter')=='inactive' ){
			$extra_fields = ', aselects, aselecteds, qupvotes, qdownvotes, aupvotes, adownvotes, qvoteds, avoteds, upvoteds, downvoteds';
			$load_filter = ' WHERE aselects = 0 AND aselecteds = 0 AND qupvotes = 0 AND qdownvotes = 0 AND aupvotes = 0 AND adownvotes = 0 AND qvoteds = 0 AND avoteds = 0 AND upvoteds = 0 AND downvoteds = 0';
		}else{
			$extra_fields = '';
			$load_filter =  '';
		}
		// Load users from database
		$users = qa_db_read_all_assoc(qa_db_query_sub('SELECT ^users.userid, ^users.handle, ^users.email, ^users.level, ^users.flags, p.points, p.qposts, p.aposts, p.cposts FROM ^users LEFT JOIN (select userid, points, qposts, aposts, cposts ' . $extra_fields . ' from ^userpoints) AS p ON p.userid=^users.userid' . $load_filter . $limit_users));
		foreach($messages as $message){
			$output .= '
				<div class="qa-error">' . $message . '</div>
			';
		}
		$output .= $page_form .'
			<form  name="admin_form" action="'.qa_self_html().'" method="post" method="post">
				<div style="display: inline-block; width: 98%; padding: 1%;">
					<div class="" style="float:left;">
						<select id="um-action" class="qa-form-wide-select input-sm" name="um-action" style="float: left;">
							<option selected="" value="none">Select Action</option>
							<option value="blockuser">Block User</option>
							<option value="removeuser">Remove User</option>
							<option value="sendconfirm">Send Email Confirmation</option>
						</select>
						<button id="do_action" class="qa-form-tall-button qa-form-tall-button-save btn btn-default" type="submit" name="do_action">Apply</button>
						<a class="btn btn-default" href=' . qa_path_html('admin/user_register') . '>Add new user</a>
					</div>
					<div style="float:right;"><a id="checkall" href="#">Check All</a> / <a id="checknone" href="#">Check None</a></div>
				</div>

				<table id="umtable" class="display" width="100%" cellspacing="0">
					<thead>
						<tr>
							<th></th>
							<th>UserName</th>
							<th>Email</th>
							<th>Level</th>
							<th>Points</th>
							<th>Confirmed</th>
							<th>Questions</th>
							<th>Answers</th>
							<th>Comments</th>
						</tr>
					</thead>
					<tfoot>
						<tr>
							<th></th>
							<th>UserName</th>
							<th>Email</th>
							<th>Level</th>
							<th>Points</th>
							<th>Confirmed</th>
							<th>Questions</th>
							<th>Answers</th>
							<th>Comments</th>

						</tr>
					</tfoot>
					<tbody>';
					foreach ($users as $user){
						$isconfirmed=($user['flags'] & QA_USER_FLAGS_EMAIL_CONFIRMED) ? 'Yes' : 'No';
						$output .= '
							<tr>
								<td><label><input id="chk-user-' . $user['handle'] . '" class="chk-user" name="chk-user-checked[]" type="checkbox" value="' .  $user['userid'] . '"></label></td>
								<td><a href="' . qa_path_html('user/' . $user['handle']) . '?state=edit">' . $user['handle'] .'</a></td>
								<td>' . $user['email'] .'</td>
								<td>' . (int)$user['level'] .'</td>
								<td>' . (int)$user['points'] .'</td>
								<td>' . $isconfirmed .'</td>
								<td>' . (int)$user['qposts'] .'</td>
								<td>' . (int)$user['aposts'] .'</td>
								<td>' . (int)$user['cposts'] .'</td>
							</tr>';
					}
					$output .= '
					</tbody>
				</table>
			</form>';
		return $output;
	}
	function admin_form()
	{
		$saved=false;
		
		if (qa_clicked('reg_user_save_button')) {
			qa_opt('um_users_per_page', (int)qa_post_text('um_users_per_page'));
			qa_opt('um_users_filter', qa_post_text('um_users_filter'));
			qa_opt('um_notification_subject', qa_post_text('um_notification_subject'));
			qa_opt('um_notification_content', qa_post_text('um_notification_content'));
			$saved=true;
		}

		$form=array(
			'ok' => null,
			
			'fields' => array(
					array(
						'label' => 'Number of users per page',
						'tags' => 'name="um_users_per_page"',
						'type' => 'number',
						'value' => (int)qa_html(qa_opt('um_users_per_page')),
						'default-value' => '0',
						'note' => 'If number of your site\'s users are very high and web page can\'t load them all, you can use this option to load fewer users per page. <b>set it to zero(0) to load all users.</b>',
					),
					array(
						'label' => 'Display users:',
						'options' => array(
							'all' => 'All users',
							'active' => 'Active Users',
							'inactive' => 'Inactive Users',
						),
						'type' => 'select',
						'value' => qa_opt('um_users_filter'),
						'default-value' => 'all',
						'tags' => 'NAME="um_users_filter"',
						'match_by' => 'key',
					),
					array(
						'type' => 'blank',
					),
					array(
						'type' => 'static',
						'value' =>'<br /><b>Options for User Registration Tool</b>',
					),
					array(
						'label' => 'Email notification subject',
						'tags' => 'name="um_notification_subject"',
						'type' => 'text',
						'default-value' => "Your Account at ^site_title",
						'value' => qa_opt('um_notification_subject'),
					),
					array(
						'label' => 'Email notification content',
						'tags' => 'name="um_notification_content"',
						'type' => 'textarea',
						'default-value' => "Hello, A new account at ^site_title is created you you. You can access it with:\r\nUsername: ^handle\r\nPassword: ^password\r\n\r\nProfile: ^profile\r\nThanks",
						'value' => qa_opt('um_notification_content'),
						'note' => 'you can use ^site_title, ^handle, ^email, ^password, ^profile variables in email subject and content.',
						'rows' => 5,
					),
					array(
						'type' => 'blank',
					),
					array(
						'type' => 'static',
						'value' =>'Visit <a href="'. qa_path_html('admin/users_management') .'"><b>User Manager</b></a>',
					),
			),
			'buttons' => array(
				array(
					'label' => 'Save Changes',
					'tags' => 'name="reg_user_save_button"',
				),
			),
			);

		return $form;
	}	

	function page_form_user_register(){
		$output = '';
		$username = '';
		$password = $this->RandomPassword();
		$email = '';
		$send_password = 1;	

		if ( (qa_clicked('register_user')) && ($this->added==false) ){
			$username = qa_post_text('handle');
			$password = qa_post_text('password');
			$email = qa_post_text('email');
			$send_password = (int)qa_post_text('send_password');
			require_once QA_INCLUDE_DIR.'qa-db-users.php';
			require_once QA_INCLUDE_DIR.'qa-app-limits.php';
			require_once QA_INCLUDE_DIR.'qa-app-users-edit.php';
			$errors=array_merge(
				qa_handle_email_filter($username, $email),
				qa_password_validate($password)
			);
			if (empty($errors)) {
				// create user
				$userid=qa_create_new_user($email, $password, $username);
				// send notification
				$this->SendRegistrationEmail($username, $email, $password);
				
				$output .= '<div class="qa-form-tall-ok">User was successfully added.</div>';
				$this->added = true;
				$output = '';
				$username = '';
				$password = $this->RandomPassword();
				$email = '';
				$send_password = 1;	
			}else{
				$this->added = false;
			}
		}

		$output .= '
			<form  name="admin_form" action="'.qa_self_html().'" method="post" method="post">
				<table class="qa-form-tall-table">
					<tbody>
						<tr>
							<td class="qa-form-tall-label">
								Username:
							</td>
						</tr>
						<tr>
							<td class="qa-form-tall-data">
								<input type="text" class="qa-form-tall-text" value="' . $username . '" id="handle" name="handle">
								<span style="color: red;">' . @$errors['handle']  . '</span>
							</td>
						</tr>
						<tr>
							<td class="qa-form-tall-label">
								Email:
							</td>
						</tr>
						<tr>
							<td class="qa-form-tall-data">
								<input type="text" class="qa-form-tall-text" value="' . $email . '" id="email" name="email">
								<span style="color: red;">' . @$errors['email']  . '</span>
							</td>
						</tr>
						<tr>
							<td class="qa-form-tall-label">
								Password:
							</td>
						</tr>
						<tr>
							<td class="qa-form-tall-data">
								<input type="text" class="qa-form-tall-text" value="' . $password . '" id="password" name="password">
								<span style="color: red;">' . @$errors['password']  . '</span>
							</td>
						</tr>
						<tr>
							<td class="qa-form-tall-data">
								<label>
									<input class="qa-form-tall-checkbox" type="checkbox" ' . ( ($send_password) ? ' checked=""' : '') . '  value="1" name="send_password">
									Send these detail to user\'s email.
								</label>
							</td>
						</tr>
					</tbody>
				</table>
				<button id="register_user" class="qa-form-tall-button" type="submit" name="register_user">Register User</button>
			</form>';
		return $output;
	}
	function RandomPassword( $length = 8, $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789' ) {
		return substr( str_shuffle( $chars ), 0, $length );
	}

	function SendRegistrationEmail($handle, $email, $password){
		require_once QA_INCLUDE_DIR.'qa-app-emails.php';
		require_once QA_INCLUDE_DIR.'qa-db-users.php';
		$subs['^site_title']=qa_opt('site_title');
		$subs['^handle']=$handle;
		$subs['^email']=$email;
		$subs['^password']=$password;
		$subs['^profile'] = qa_path_absolute('user/'.$handle);
		$subject = qa_opt('reg_user_subject');
		$body = qa_opt('reg_user_body');

        qa_send_email(array(
            'fromemail' => qa_opt('from_email'),
            'fromname' => qa_opt('site_title'),
            'toemail' => $email,
            'toname' => $handle,
            'subject' => strtr($subject, $subs),
            'body' => strtr($body, $subs),
            'html' => false,
        ));
	}

}

