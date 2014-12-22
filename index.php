<?php
/*

Plugin Name: Anon Spam Destroyer
Plugin URI: http://www.google.com
Description: Spam combatter for anon
Author: Anon
Version: 0.0.1

This plugin is based heavily on Ryan Hellyer's plugin "Spam Destroyer": http://pixopoint.com/products/spam-destroyer/
There is no relation between author of this plugin (Anon) and Mr Hellyer.

This software is distributed under the GNU General Public License.

*/

class AnonSpamDestroyer {
	
	public $spam_key; // Key used for confirmation of bot-like behaviour
	public $speed = 5; // Will be killed as spam if posted faster than this
	private $_hidden_email = 'anonymous@internet.net';
	private $_hidden_author = 'Anonymous';
	
	public function __construct() {

		// Add filters
		add_filter( 'preprocess_comment',			array( $this, 'check_for_comment_evilness' ) ); // Support for regular post/page comments
		add_filter( 'comment_form_default_fields',	array( $this, 'setup_comment_fields' ) ); // Remove URL field from comment form

		// Add to hooks
		add_action( 'init',							array( $this, 'set_key' ) );
		add_action( 'comment_form',					array( $this, 'extra_input_field' ) ); // WordPress comments page

	}
	
	public function setup_comment_fields( $fields ) {
		if( isset( $fields[ 'url' ] ) ) unset( $fields[ 'url' ] );
		if( isset( $fields[ 'author' ] ) ) $fields[ 'author' ] = '<p class="comment-form-author"><label for="author">'.__('Name').'</label><input id="author" name="author" type="text" value="'. $this->_hidden_author .'" size="30" /></p>';
		if( isset( $fields[ 'email' ] ) ) $fields[ 'email' ] = '<input id="email" name="email" type="hidden" value="'. $this->_hidden_email .'" />';
  
		return $fields;
	}

	public function set_key() {

		// set spam key using home_url() and new nonce as salt
		$string = home_url() . wp_create_nonce( 'anon-spam-killer' );
		$this->spam_key = md5( $string );

	}

	public function load_payload() {

	// Load the payload
	wp_enqueue_script(
		'kill_it_dead',
		plugins_url( 'kill.js',  __FILE__ ),
		'',
		'1.2',
		true
	);

	// Set the key as JS variable for use in the payload
	wp_localize_script(
		'kill_it_dead',
		'spam_destroyer',
		array(
			'key' => $this->spam_key
		)
	);

	}

	public function extra_input_field() {
		echo '<input type="hidden" id="killer_value" name="killer_value" value="' . md5( rand( 0, 999 ) ) . '"/>';
		echo '<noscript>' . __( 'Sorry, but you are required to use a javascript enabled brower to comment here.', 'anon-spam-killer' ) . '</noscript>';

		// Enqueue the payload - placed here so that it is ONLY used when on a page utilizing the plugin
		$this->load_payload();
	}

	public function check_for_comment_evilness( $comment ) {

		// If the user is logged in, then they're clearly trusted, so continue without checking
		if ( is_user_logged_in() )
			return $comment;

		$type = $comment['comment_type'];

		// Check the hidden input field against the key
		if ( $_POST['killer_value'] != $this->spam_key ) {
			$this->kill_spam_dead( $comment ); // BOOM! Silly billy didn't have the correct input field so killing it before it reaches your eyes.
		}
		
		// Check if author e-mail is same as hidden email:
		if ( $_POST['email'] != $this->_hidden_email ) {
			$this->kill_spam_dead( $comment ); // BOOM! Spammer must use our hidden email address
		}
		
		// Check if url value is sent:
		if ( isset( $_POST[ 'url' ] ) ) {
			$this->kill_spam_dead( $comment ); // BOOM! We don't like forms with the url field
		}

		// Check for cookies presence
		// This is not compatible with NO SCRIPT and the TOR Browser Bundle.
		// Activate with care

		/*
		if ( isset( $_COOKIE[ $this->spam_key ] ) ) {
			// If time not set correctly, then assume it's spam
			if ( $_COOKIE[$this->spam_key] > 1 && ( ( time() - $_COOKIE[$this->spam_key] ) < $this->speed ) ) {
				$this->kill_spam_dead( $comment ); // Something's up, since the commenters cookie time frame doesn't match ours
			}
		} else {
			$this->kill_spam_dead( $comment ); // Ohhhh! Cookie not set, so killing the little dick before it gets through!
		}
		*/


		// YAY! It's a miracle! Something actually got listed as a legit comment :) W00P W00P!!!
		return $comment;
	}
	
	public function kill_spam_dead( $comment ) {

		// Set as spam
		add_filter( 'pre_comment_approved', create_function( '$a', 'return \'spam\';' ) );


		// Return the comment anyway, since it's nice to keep it in the spam queue in case we messed up
		return $comment;

	}
}

new AnonSpamDestroyer();
