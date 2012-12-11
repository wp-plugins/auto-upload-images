<?php
/*
Plugin Name: Auto Upload Images
Plugin URI: http://p30design.net/1391/08/wp-auto-upload-images.html
Description: Automatically upload external images of a post to wordpress upload directory
Version: 1.3
Author: Ali Irani
Author URI: http://p30design.net
License: GPLv2 or later
*/

add_action( 'save_post', 'wp_auto_upload_images' );

/**
 * Automatically upload external images of a post to wordpress upload directory
 *
 * @param $post_id
 */
function wp_auto_upload_images( $post_id ) {

	if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) 
		return;
		
	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) 
        return;
	
	if ( false !== wp_is_post_revision($post_id) )
		return;
		
	global $wpdb;
	
	$content = $wpdb->get_var( "SELECT post_content FROM wp_posts WHERE ID='$post_id' LIMIT 1" );
	$images_url = wp_get_images_url($content);
	
	if($images_url) {
		foreach ($images_url as $image_url) {
			if(!wp_is_myurl($image_url) && $new_image_url = wp_save_image($image_url, $post_id)) {
				$new_images_url[] = $new_image_url;
				unset($new_image_url);
			} else {
				$new_images_url[] = $image_url;
			}
		}
		
		$total = count($new_images_url);
		
		for ($i = 0; $i <= $total-1; $i++) {
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
 * @param $content
 * @return array of urls or false
 */
function wp_get_images_url( $content ) {
	preg_match_all('/<img[^>]*src=("|\')([^(\?|#|"|\')]*)(\?|#)?[^("|\')]*("|\')[^>]*\/>/', $content, $urls, PREG_SET_ORDER);
	
	if(is_array($urls)) {
		foreach ($urls as $url)
			$images_url[] = $url[2];
	}

	if (is_array($images_url)) {
		$images_url = array_unique($images_url);
		rsort($images_url);
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
	$url = wp_get_base_url($url);
	$myurl = wp_get_base_url(get_bloginfo('url'));
	
	return ($myurl == $url) ? true : false;
}

/**
 * Give a $url and return Base of a $url
 *
 * @param $url
 * @return $url
 */
function wp_get_base_url( $url ) {
	$url_pattern = "/^(www(2|3)?\.)/i";
	$url = parse_url($url, PHP_URL_HOST);
	$temp = preg_split('/^(www(2|3)?\.)/i', $url, -1, PREG_SPLIT_NO_EMPTY);
	
	return $url = $temp[0];
}

/**
 * Save image on wp_upload_dir
 * Add image to Media Library and attach to post
 *
 * @param $url
 * @param $post_id
 * @return new $url or false
 */
function wp_save_image($url, $post_id = 0) {
	$image_name = basename($url);
	
	$upload_dir = wp_upload_dir(date('Y/m'));
	$path = $upload_dir['path'] . '/' . $image_name;
	$new_image_url = $upload_dir['url'] . '/' . $image_name;
	$file_exists = true;
	$i = 0;
	
	while ( $file_exists ) {
		if ( file_exists($path) ) {
			if ( wp_get_exfilesize($url) == filesize($path) ) {
				return false;
			} else {
				$i++;
				$path = $upload_dir['path'] . '/' . $i . '_' . $image_name;	
				$new_image_url = $upload_dir['url'] . '/' . $i . '_' . $image_name;
			}
		} else {
			$file_exists = false;
		}
	}
	
	if(function_exists('curl_init')) {
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);     
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2); 
		$data = curl_exec($ch);
		curl_close($ch);
		file_put_contents($path, $data);
		
		$wp_filetype = wp_check_filetype($new_image_url);
		$attachment = array(
			'guid' => $new_image_url, 
			'post_mime_type' => $wp_filetype['type'],
			'post_title' => preg_replace('/\.[^.]+$/', '', basename($new_image_url)),
			'post_content' => '',
			'post_status' => 'inherit'
		);
		wp_insert_attachment($attachment, $path, $post_id);
		
		return $new_image_url;
	} else {
		return false;
	}
}

/**
 * return size of external file
 *
 * @param $file
 * @return $size
 */
function wp_get_exfilesize( $file ) {
	$ch = curl_init($file);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);     
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2); 
    $data = curl_exec($ch);
    curl_close($ch);

    if (preg_match('/Content-Length: (\d+)/', $data, $matches))
        return $contentLength = (int)$matches[1];
	else
		return false;
}