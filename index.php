<?php
/*
Plugin Name: Auto Upload Images
Plugin URI: http://p30design.net
Description: Automatic upload external images of content to upload internal server
Version: 1.0
Author: Ali Irani
Author URI: http://p30design.net
License: GPLv2 or later
*/

add_action( 'save_post', 'wp_auto_upload_images' );

/**
 * Automatic upload external images of content to upload internal server
 *
 * @param $post_id
 */
function wp_auto_upload_images($post_id) {

	if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) 
		return;
		
	global $wpdb;
	
	$content = $wpdb->get_var( "SELECT post_content FROM wp_posts WHERE ID='$post_id' LIMIT 1" );
	$images_url = wp_get_images_url($post_id, $content);
	
	if($images_url) {
		foreach ($images_url as $image_url) {
			if(!wp_is_myurl($image_url) && wp_save_image($image_url))
				$new_images_url[] = wp_save_image($image_url);
			else
				$new_images_url[] = $image_url;
		}
	
		$total = count($new_images_url);
		
		for($i=0; $i<=$total-1; $i++) {
			$content = preg_replace('/'. preg_quote($images_url[$i], '/') .'/', $new_images_url[$i], $content);
		}
		
		remove_action( 'save_post', 'wp_auto_upload_images' );
-		wp_update_post( array('ID' => $post_id, 'post_content' => $content) );
-		add_action( 'save_post', 'wp_auto_upload_images' );
	}
}

/**
 * Detect url of images which exists in content
 *
 * @param $post_id
 * @return array of urls or false
 */
function wp_get_images_url( $post_id, $content ) {
	preg_match_all('/<img[^>]*src=("|\')([^(\?|#|"|\')]*)(\?|#)?[^("|\')]*("|\')[^>]*\/>/', $content, $urls, PREG_SET_ORDER);
	
	if(is_array($urls)) {
		foreach ($urls as $url)
			$images_url[] = $url[2];
	}
	
	return isset($images_url) ? $images_url : false;
}

/**
 * Check url is internal or external
 *
 * @param $url
 * @return true or false
 */
function wp_is_myurl( $url ) {
	$url_pattern = "/https?:\/\/((.*?)\.)*([a-z0-9\-]+\.[a-z]+)\/?.*/i";
	$myurl = get_bloginfo('url');
	
	if(preg_match($url_pattern, $myurl, $m))
		$myurl = $m[3];
	else
		return false;
	
	if(preg_match($url_pattern, $url, $m))
		$target_url = $m[3];
	else
		return false;
	
	return ($myurl == $target_url) ? true : false;
}

/**
 * Save image on wp_upload_dir
 *
 * @param $url
 * @return new $url or false
 */
function wp_save_image($url) {
	if(preg_match('/\/([a-z0-9\-_\+]+\.[a-z0-9]+)$/i', $url, $m))
		$image_name = $m[1];
	else 
		return false;
	
	$upload_dir = wp_upload_dir(date('Y/m'));
	$path = $upload_dir['path'] . '/' . $image_name;
	
	if(function_exists('curl_init')) {
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$data = curl_exec($ch);
		curl_close($ch);
		file_put_contents($path, $data);
		
		return $upload_dir['url'] . '/' . $image_name;
	} else {
		return false;
	}
}
