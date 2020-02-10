<?php
/**
 * Plugin Name: bbPress Slack Integration
 * Description: Send notifications of new bbPress topics and replies to a Slack channel.
 * Author: John Pierce forked from Josh Pollock
 * Author URI: http://johnparnellpierce.com
 * Version: 0.3.2
 * Plugin URI: http://kloxong.org
 * License: GNU GPLv2+
 */
/**
 * Copyright (c) 2014-2020 Josh Pollock (email : jpollock412@gmail.com)
 * Copyright (c) 2020 John Pierce (email : john@johnparnellpierce.com)	
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

add_action( 'bbp_new_reply',  'bbp_slack_integration', 20, 2 );
add_action( 'bbp_new_topic',  'bbp_slack_integration', 20, 2 );
function bbp_slack_integration(  $id, $parent_id ) {
	$url = get_option( 'bbpress_slack_webhook', false );
	if ( $url ) {
		$post = get_post( $id );
		$type = $post->post_type;
		$author = get_the_author_meta('display_name',$post->post_author);

		if ( 'reply' == $type ) {
			$link = get_permalink( $parent_id );
			$link .= '#post-'.$id;
			$title = 'Reply: '. get_the_title( $parent_id );
		}
		else {
			$link = get_permalink( $id );
			$title = $post->post_title;
		}

		$link = htmlspecialchars( $link );


		$excerpt = wp_trim_words( $post->post_content );
		if ( 127 < strlen( $excerpt ) ) {
			$excerpt = substr( $excerpt, 0, 127 );
		}

		$payload = array(
			'text'        => __( '<'.$link.'|'.$title.' >', 'bbpress-slack' ),
			'username' => 'Forumbot',
			'attachments' => array(array(
				'fallback' => $link,
				'color'    => '#ff000',
				'fields'   => array(array(
					'title' => $author. ' wrote:',
					'value' => $excerpt,
				)
				)
			),
			)					   
		);
		$output  = 'payload=' . json_encode( $payload );
		
		$pluginlog = plugin_dir_path(__FILE__).'debug.log';
		$errmessage = 'Payload: '.$output.PHP_EOL;
// Uncomment to log payload to slack
//		error_log($errmessage, 3, $pluginlog);
	

		$response = wp_remote_post( $url, array(
			'body' => $output,
		) );

		/**
		 * Runs after the data is sent.
		 *
		 * @param array $response Response from server.
		 *
		 * @since 0.3.0
		 */
		do_action( 'bbp_slack_integration_post_send', $response );

	}

}

/**
 * Load admin class if admin
 *
 * @since 0.2.0
 */
if ( is_admin() ) {
	new bbp_slack_integration_admin();
}

class bbp_slack_integration_admin {

	private $option_name = 'bbpress_slack_webhook';
	private $nonce_name = '_bbpress_slack_nonce';
	private $nonce_action = '_bbpress_slack_nonce_action';

	function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
	}

	/**
	 * Add the menu
	 *
	 * @since 0.2.0
	 */
	function menu() {
		add_options_page(
			__( 'bbPress Slack Integration', 'bbpress-slack' ),
			__( 'bbPress Slack Integration', 'bbpress-slack' ),
			'manage_options',
			'bbp_slack',
			array( $this, 'page' )
		);
	}

	/**
	 * Render admin page and handle saving.
	 *
	 * @TODO Use AJAX for saving
	 *
	 * @since 0.2.0
	 *
	 * @return string the admin page.
	 */
	function page() {
		echo $this->instructions();
		echo $this->form();
		if ( isset( $_POST ) && isset( $_POST[ $this->nonce_name ] ) && wp_verify_nonce( $_POST[ $this->nonce_name ], $this->nonce_action ) ) {
			if ( isset( $_POST[ 'slack-hook' ] )) {
				$option = esc_url_raw( $_POST[ 'slack-hook' ] );
				$option = filter_var( $option, FILTER_VALIDATE_URL );
				if ( $option ) {
					update_option( $this->option_name, $option );
					if ( isset( $_POST[ '_wp_http_referer' ] ) && $_POST[ '_wp_http_referer' ] ) {
						$location = $_POST['_wp_http_referer'];
						die( '<script type="text/javascript">'
						     . 'document.location = "' . str_replace( '&amp;', '&', esc_js( $location ) ) . '";'
						     . '</script>' );
					}
				}
			}

		}

	}

	/**
	 * Admin form
	 *
	 * @since 0.2.0
	 *
	 * @return string The form.
	 */
	function form() {
		$out[] = '<form id="bbp_slack_integration" method="POST" action="options-general.php?page=bbp_slack">';
		$out[] = wp_nonce_field( $this->nonce_action, $this->nonce_name, true, false );
		$url = get_option( $this->option_name, '' );
		$out[] = '<input id="slack-hook" name="slack-hook"type="text" value="'.esc_url( $url ).'"></input>';
		$out[] = '<input type="submit" class="button-primary">';
		$out[] = '</form>';

		return implode( $out );

	}

	/**
	 * Show instructions.
	 *
	 * @since 0.2.0
	 *
	 * @return string The instructions.
	 */
	function instructions() {
		$header = '<h3>' . __( 'Instructions:', 'bbpress-slack' ) .'</h3>';
		$instructions = array(
			__( 'Go To https://<your-team-name>.slack.com/services/new/incoming-webhook', 'bbpress-slack' ),
			__( ' Create a new webhook', 'bbpress-slack' ),
			__( 'Set a channel to receive the notifications', 'bbpress-slack' ),
			__( 'Copy the URL for the webhook	', 'bbpress-slack' ),
			__( 'Past the URL into the field below and click submit', 'bbpress-slack' ),
		);

		return $header. "<ol><li>" .implode( "</li><li>", $instructions ) . "</li></ol>";

	}

}
