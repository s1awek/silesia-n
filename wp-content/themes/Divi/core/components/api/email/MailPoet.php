<?php

/**
 * Wrapper for MailPoet's API.
 *
 * @since   3.0.63
 *
 * @package ET\Core\API\Email
 */
class ET_Core_API_Email_MailPoet extends ET_Core_API_Email_Provider {

	public static $CAN_USE_MPV3;
	public static $PLUGIN_REQUIRED;

	/**
	 * @inheritDoc
	 */
	public $name = 'MailPoet';

	/**
	 * @inheritDoc
	 */
	public $slug = 'mailpoet';

	public function __construct( $owner = '', $account_name = '', $api_key = '' ) {
		parent::__construct( $owner, $account_name, $api_key );

		if ( null === self::$CAN_USE_MPV3 ) {
			self::$PLUGIN_REQUIRED = esc_html__( 'MailPoet plugin is either not installed or not activated.', 'et_core' );
		}
	}

	/**
	 * Get subscriber lists from legacy MailPoet v2.x and save them to the database.
	 */
	protected function _fetch_subscriber_lists_legacy() {
		$lists           = array();
		$list_model      = WYSIJA::get( 'list', 'model' );
		$all_lists_array = $list_model->get( array( 'name', 'list_id' ), array( 'is_enabled' => '1' ) );

		foreach ( $all_lists_array as $list_details ) {
			$lists[ $list_details['list_id'] ]['name'] = sanitize_text_field( $list_details['name'] );

			$user_model            = WYSIJA::get( 'user_list', 'model' );
			$all_subscribers_array = $user_model->get( array( 'user_id' ), array( 'list_id' => $list_details['list_id'] ) );

			$subscribers_count                                      = count( $all_subscribers_array );
			$lists[ $list_details['list_id'] ]['subscribers_count'] = sanitize_text_field( $subscribers_count );
		}

		$this->data['is_authorized'] = true;

		if ( ! empty( $lists ) ) {
			$this->data['lists'] = $lists;
		}

		$this->save_data();

		return 'success';
	}

	/**
	 * Add new subscriber with MailPoet v2.x
	 *
	 * @return string 'success' if successful, an error message otherwise.
	 */
	public function _subscribe_legacy( $args, $url = '' ) {
		global $wpdb;
		$user_table       = $wpdb->prefix . 'wysija_user';
		$user_lists_table = $wpdb->prefix . 'wysija_user_list';

		// get the ID of subscriber if they're in the list already
		$sql_user_id        = "SELECT user_id FROM {$user_table} WHERE email = %s";
		$sql_args           = array( et_sanitized_previously( $args['email'] ) );
		$subscriber_id      = $wpdb->get_var( $wpdb->prepare( $sql_user_id, $sql_args ) );
		$already_subscribed = 0;

		// if current email is subscribed, then check whether it subscribed to the current list
		if ( ! empty( $subscriber_id ) ) {
			$sql_is_subscribed = "SELECT COUNT(*) FROM {$user_lists_table} WHERE user_id = %s AND list_id = %s";
			$sql_args          = array(
				$subscriber_id,
				et_sanitized_previously( $args['list_id'] ),
			);

			$already_subscribed = (int) $wpdb->get_var( $wpdb->prepare( $sql_is_subscribed, $sql_args ) );
		}

		// if email is not subscribed to current list, then subscribe.
		if ( 0 === $already_subscribed ) {
			$new_user = array(
				'user'      => array(
					'email'     => et_sanitized_previously( $args['email'] ),
					'firstname' => et_sanitized_previously( $args['name'] ),
					'lastname'  => et_sanitized_previously( $args['last_name'] ),
				),
				'user_list' => array(
					'list_ids' => array( et_sanitized_previously( $args['list_id'] ) ),
				),
			);

			$mailpoet_class = WYSIJA::get( 'user', 'helper' );
			$error_message  = $mailpoet_class->addSubscriber( $new_user );
			$error_message  = is_int( $error_message ) ? 'success' : $error_message;
		} else {
			$error_message = esc_html__( 'Already Subscribed', 'bloom' );
		}

		return $error_message;
	}

	/**
	 * @inheritDoc
	 */
	public function get_account_fields() {
		return array();
	}

	/**
	 * @inheritDoc
	 */
	public function get_data_keymap( $keymap = array(), $custom_fields_key = '' ) {
		$keymap = array(
			'list'       => array(
				'list_id' => 'id',
				'name'    => 'name',
			),
			'subscriber' => array(
				'name'      => 'first_name',
				'last_name' => 'last_name',
				'email'     => 'email',
			),
		);

		return parent::get_data_keymap( $keymap, $custom_fields_key );
	}

	/**
	 * @inheritDoc
	 */
	public function fetch_subscriber_lists() {
		if ( class_exists( 'WYSIJA' ) ) {
			$result = $this->_fetch_subscriber_lists_legacy();
		} else {
			$result = self::$PLUGIN_REQUIRED;
		}

		return $result;
	}

	/**
	 * @inheritDoc
	 */
	public function subscribe( $args, $url = '' ) {
		if ( class_exists( 'WYSIJA' ) ) {
			$result = $this->_subscribe_legacy( $args, $url );
		} else {
			$result = esc_html__( 'An error occurred. Please try again later.', 'et_core' );
			ET_Core_Logger::error( self::$PLUGIN_REQUIRED );
		}

		return $result;
	}
}
