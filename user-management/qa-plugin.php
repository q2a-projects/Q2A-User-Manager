<?php
/*
	Plugin Name: User Management Tool
	Plugin URI: http://QA-Themes.com
	Plugin Description: 
	Plugin Version: 1.1
	Plugin Date: 
	Plugin Author: Towhid Nategheian
	Plugin License: GPLv2
	Plugin Minimum Question2Answer Version: 1.5
	Plugin Update Check URI: 
*/

if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
	header('Location: ../../');
	exit;
}

define('plugin_dir_um', dirname( __FILE__ ));
require_once(plugin_dir_um. '/functions.php');

qa_register_plugin_module('page', 'admin-um.php', 'admin_um', 'um');
qa_register_plugin_layer('layer.php', 'um Layer');


function um_post_delete_recursive($postid){
	require_once QA_INCLUDE_DIR.'qa-app-admin.php';
	require_once QA_INCLUDE_DIR.'qa-db-admin.php';
	require_once QA_INCLUDE_DIR.'qa-db-selects.php';
	require_once QA_INCLUDE_DIR.'qa-app-format.php';
	require_once QA_INCLUDE_DIR.'qa-app-posts.php';

	global $um_posts_deleted ; 

	if (is_null($um_posts_deleted)) {
		$um_posts_deleted = array() ;
	}

	if (in_array($postid, $um_posts_deleted)){
		return;
	}
	
	$oldpost=qa_post_get_full($postid, 'QAC');
	
	if (!$oldpost['hidden']) {
		qa_post_set_hidden($postid, true, null);
		$oldpost=qa_post_get_full($postid, 'QAC');
	}
	
	switch ($oldpost['basetype']) {
		case 'Q':
			$answers=qa_post_get_question_answers($postid);
			$commentsfollows=qa_post_get_question_commentsfollows($postid);
			$closepost=qa_post_get_question_closepost($postid);
			
			if (count($answers) ){
				foreach ($answers as $answer) {
					um_post_delete_recursive($answer['postid']);
				}
			}

			if (count($commentsfollows)){
				foreach ($commentsfollows as $commentsfollow) {
					um_post_delete_recursive($commentsfollow['postid']);
				}
			}
			if (!in_array($oldpost['postid'], $um_posts_deleted)){
				qa_question_delete($oldpost, null, null, null, $closepost);
				$um_posts_deleted[] = $oldpost['postid'] ;
			}
			break;
			
		case 'A':
			$question=qa_post_get_full($oldpost['parentid'], 'Q');
			$commentsfollows=qa_post_get_answer_commentsfollows($postid);

			if (count($commentsfollows)){
				foreach ($commentsfollows as $commentsfollow) {
					um_post_delete_recursive($commentsfollow['postid']);
				}
			}
			if (!in_array($oldpost['postid'], $um_posts_deleted)){
				qa_answer_delete($oldpost, $question, null, null, null);
				$um_posts_deleted[] = $oldpost['postid'] ;
			}
			break;
			
		case 'C':
			$parent=qa_post_get_full($oldpost['parentid'], 'QA');
			$question=qa_post_parent_to_question($parent);
			if (!in_array($oldpost['postid'], $um_posts_deleted)){
				qa_comment_delete($oldpost, $question, $parent, null, null, null);
				$um_posts_deleted[] = $oldpost['postid'] ;
			}
			break;
	}
}