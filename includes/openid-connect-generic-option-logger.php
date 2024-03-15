<?php
/**
 * Plugin logging class.
 *
 * @package   OpenID_Connect_Generic
 * @category  Logging
 * @author    Jonathan Daggerhart <jonathan@daggerhart.com>
 * @copyright 2015-2023 daggerhart
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0+
 */

/**
 * OpenID_Connect_Generic_Option_Logger class.
 *
 * Simple class for logging messages to the options table.
 *
 * @package  OpenID_Connect_Generic
 * @category Logging
 */
class OpenID_Connect_Generic_Option_Logger {

	/**
	 * Thw WordPress option name/key.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'openid-connect-generic-logs';

	/**
	 * The default message type.
	 *
	 * @var string
	 */
	private $default_message_type = 'none';

	/**
	 * The number of items to keep in the log.
	 *
	 * @var int
	 */
	private $log_limit = 1000;

	/**
	 * Whether or not logging is enabled.
	 *
	 * @var bool
	 */
	private $logging_enabled = true;

	/**
	 * Internal cache of logs.
	 *
	 * @var array
	 */
	private $logs;

	/**
	 * Setup the logger according to the needs of the instance.
	 *
	 * @param string|null    $default_message_type The log message type.
	 * @param bool|TRUE|null $logging_enabled      Whether logging is enabled.
	 * @param int|null       $log_limit            The log entry limit.
	 */
	public function __construct( $default_message_type = null, $logging_enabled = null, $log_limit = null ) {
		if ( ! is_null( $default_message_type ) ) {
			$this->default_message_type = $default_message_type;
		}
		if ( ! is_null( $logging_enabled ) ) {
			$this->logging_enabled = boolval( $logging_enabled );
		}
		if ( ! is_null( $log_limit ) ) {
			$this->log_limit = intval( $log_limit );
		}
	}

	/**
	 * Save an array of data to the logs.
	 *
	 * @param string|array<string, string>|WP_Error $data            The log message data.
	 * @param string|null                           $type            The log message type.
	 * @param float|null                            $processing_time Optional event processing time.
	 * @param int|null                              $time            The log message timestamp (default: time()).
	 * @param int|null                              $user_ID         The current WordPress user ID (default: get_current_user_id()).
	 * @param string|null                           $request_uri     The related HTTP request URI (default: $_SERVER['REQUEST_URI']|'Unknown').
	 *
	 * @return bool
	 */
	public function log( $data, $type = null, $processing_time = null, $time = null, $user_ID = null, $request_uri = null ) {
		if ( boolval( $this->logging_enabled ) ) {
			$logs = $this->get_logs();
			$logs[] = $this->make_message( $data, $type, $processing_time, $time, $user_ID, $request_uri );
			$logs = $this->upkeep_logs( $logs );
			return $this->save_logs( $logs );
		}

		return false;
	}

	/**
	 * Retrieve all log messages.
	 *
	 * @return array
	 */
	public function get_logs() {
		if ( empty( $this->logs ) ) {
			$this->logs = get_option( self::OPTION_NAME, array() );
		}

		// Call the upkeep_logs function to give the appearance that logs have been reduced to the $this->log_limit.
		// The logs are actually limited during a logging action but the logger isn't available during a simple settings update.
		return $this->upkeep_logs( $this->logs );
	}

	/**
	 * Get the name of the option where this log is stored.
	 *
	 * @return string
	 */
	public function get_option_name() {
		return self::OPTION_NAME;
	}

	/**
	 * Create a message array containing the data and other information.
	 *
	 * @param string|array<string, string>|WP_Error $data            The log message data.
	 * @param string|null                           $type            The log message type.
	 * @param float|null                            $processing_time Optional event processing time.
	 * @param int|null                              $time            The log message timestamp (default: time()).
	 * @param int|null                              $user_ID         The current WordPress user ID (default: get_current_user_id()).
	 * @param string|null                           $request_uri     The related HTTP request URI (default: $_SERVER['REQUEST_URI']|'Unknown').
	 *
	 * @return array
	 */
	private function make_message( $data, $type, $processing_time, $time, $user_ID, $request_uri ) {
		// Determine the type of message.
		if ( empty( $type ) ) {
			$type = $this->default_message_type;

			if ( is_array( $data ) && isset( $data['type'] ) ) {
				$type = $data['type'];
				unset( $data['type'] );
			}

			if ( is_wp_error( $data ) ) {
				$type = $data->get_error_code();
				$data = $data->get_error_message( $type );
			}
		}

		if ( empty( $request_uri ) ) {
			$request_uri = ( ! empty( $_SERVER['REQUEST_URI'] ) ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : 'Unknown';
			$request_uri = preg_replace( '/code=([^&]+)/i', 'code=', $request_uri );
		}

		// Construct the message.
		$message = array(
			'type'            => $type,
			'time'            => ! empty( $time ) ? $time : time(),
			'user_ID'         => ! is_null( $user_ID ) ? $user_ID : get_current_user_id(),
			'uri'             => $request_uri,
			'data'            => $data,
			'processing_time' => $processing_time,
		);

		return $message;
	}

	/**
	 * Keep the log count under the limit.
	 *
	 * @param array $logs The plugin logs.
	 *
	 * @return array
	 */
	private function upkeep_logs( $logs ) {
		$items_to_remove = count( $logs ) - $this->log_limit;

		if ( $items_to_remove > 0 ) {
			// Only keep the last $log_limit messages from the end.
			$logs = array_slice( $logs, $items_to_remove );
		}

		return $logs;
	}

	/**
	 * Save the log messages.
	 *
	 * @param array $logs The array of log messages.
	 *
	 * @return bool
	 */
	private function save_logs( $logs ) {
		// Save the logs.
		$this->logs = $logs;
		return update_option( self::OPTION_NAME, $logs, false );
	}

	/**
	 * Clear all log messages.
	 *
	 * @return void
	 */
	public function clear_logs() {
		$this->save_logs( array() );
	}

	/**
	 * Get a simple html table of all the logs.
	 *
	 * @param array $logs The array of log messages.
	 *
	 * @return string
	 */
	public function get_logs_table( $logs = array() ) {
		if ( empty( $logs ) ) {
			$logs = $this->get_logs();
		}
		$logs = array_reverse( $logs );

		ini_set( 'xdebug.var_display_max_depth', '-1' );

		ob_start();
		?>
		<table id="logger-table" class="wp-list-table widefat fixed striped posts">
			<thead>
				<th class="col-details"><?php esc_html_e( 'Details', 'daggerhart-openid-connect-generic' ); ?></th>
				<th class="col-data"><?php esc_html_e( 'Data', 'daggerhart-openid-connect-generic' ); ?></th>
			</thead>
			<tbody>
			<?php foreach ( $logs as $log ) { ?>
				<tr>
					<td class="col-details">
						<div>
							<label><?php esc_html_e( 'Date', 'daggerhart-openid-connect-generic' ); ?></label>
							<?php print esc_html( ! empty( $log['time'] ) ? gmdate( 'Y-m-d H:i:s', $log['time'] ) : '' ); ?>
						</div>
						<div>
							<label><?php esc_html_e( 'Type', 'daggerhart-openid-connect-generic' ); ?></label>
							<?php print esc_html( ! empty( $log['type'] ) ? $log['type'] : '' ); ?>
						</div>
						<div>
							<label><?php esc_html_e( 'User', 'daggerhart-openid-connect-generic' ); ?>: </label>
							<?php print esc_html( ( get_userdata( $log['user_ID'] ) ) ? get_userdata( $log['user_ID'] )->user_login : '0' ); ?>
						</div>
						<div>
							<label><?php esc_html_e( 'URI ', 'daggerhart-openid-connect-generic' ); ?>: </label>
							<?php print esc_url( ! empty( $log['uri'] ) ? $log['uri'] : '' ); ?>
						</div>
						<div>
							<label><?php esc_html_e( 'Response&nbsp;Time&nbsp;(sec)', 'daggerhart-openid-connect-generic' ); ?></label>
							<?php print esc_html( ! empty( $log['response_time'] ) ? $log['response_time'] : '' ); ?>
						</div>
					</td>
					<td class="col-data"><pre><?php var_dump( ! empty( $log['data'] ) ? $log['data'] : '' ); ?></pre></td>
				</tr>
			<?php } ?>
			</tbody>
		</table>
		<?php
		$output = ob_get_clean();

		return $output;
	}
}
