<?php
/**
 * Plugin logging class.
 *
 * @package   Hello_Login
 * @category  Logging
 * @author    Jonathan Daggerhart <jonathan@daggerhart.com>
 * @copyright 2015-2020 daggerhart
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0+
 */

/**
 * Hello_Login_Option_Logger class.
 *
 * Simple class for logging messages to the options table.
 *
 * @package  Hello_Login
 * @category Logging
 */
class Hello_Login_Option_Logger {

	/**
	 * Thw WordPress option name/key.
	 *
	 * @var string
	 */
	private $option_name;

	/**
	 * The default message type.
	 *
	 * @var string
	 */
	private $default_message_type;

	/**
	 * The number of items to keep in the log.
	 *
	 * @var int
	 */
	private $log_limit;

	/**
	 * Whether or not logging is enabled.
	 *
	 * @var bool
	 */
	private $logging_enabled;

	/**
	 * Internal cache of logs.
	 *
	 * @var array
	 */
	private $logs;

	/**
	 * Setup the logger according to the needs of the instance.
	 *
	 * @param string    $option_name          The plugin log WordPress option name.
	 * @param string    $default_message_type The log message type.
	 * @param bool|TRUE $logging_enabled      Whether logging is enabled.
	 * @param int       $log_limit            The log entry limit.
	 */
	public function __construct( $option_name, $default_message_type = 'none', $logging_enabled = true, $log_limit = 1000 ) {
		$this->option_name = $option_name;
		$this->default_message_type = $default_message_type;
		$this->logging_enabled = boolval( $logging_enabled );
		$this->log_limit = intval( $log_limit );
	}

	/**
	 * Subscribe logger to a set of filters.
	 *
	 * @param array|string $filter_names The array, or string, of the name(s) of an filter(s) to hook the logger into.
	 * @param int          $priority     The WordPress filter priority level.
	 *
	 * @return void
	 */
	public function log_filters( $filter_names, $priority = 10 ) {
		if ( ! is_array( $filter_names ) ) {
			$filter_names = array( $filter_names );
		}

		foreach ( $filter_names as $filter ) {
			add_filter( $filter, array( $this, 'log_hook' ), $priority );
		}
	}

	/**
	 * Subscribe logger to a set of actions.
	 *
	 * @param array|string $action_names The array, or string, of the name(s) of an action(s) to hook the logger into.
	 * @param int          $priority     The WordPress action priority level.
	 *
	 * @return void
	 */
	public function log_actions( $action_names, $priority ) {
		if ( ! is_array( $action_names ) ) {
			$action_names = array( $action_names );
		}

		foreach ( $action_names as $action ) {
			add_filter( $action, array( $this, 'log_hook' ), $priority );
		}
	}

	/**
	 * Log the data.
	 *
	 * @param mixed $arg1 The hook argument.
	 *
	 * @return mixed
	 */
	public function log_hook( $arg1 = null ) {
		$this->log( func_get_args(), current_filter() );
		return $arg1;
	}

	/**
	 * Save an array of data to the logs.
	 *
	 * @param mixed $data The data to be logged.
	 * @param mixed $type The type of log message.
	 *
	 * @return bool
	 */
	public function log( $data, $type = null ) {
		if ( boolval( $this->logging_enabled ) ) {
			$logs = $this->get_logs();
			$logs[] = $this->make_message( $data, $type );
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
			$this->logs = get_option( $this->option_name, array() );
		}

		return $this->logs;
	}

	/**
	 * Get the name of the option where this log is stored.
	 *
	 * @return string
	 */
	public function get_option_name() {
		return $this->option_name;
	}

	/**
	 * Create a message array containing the data and other information.
	 *
	 * @param mixed $data The log message data.
	 * @param mixed $type The log message type.
	 *
	 * @return array
	 */
	private function make_message( $data, $type ) {
		// Determine the type of message.
		if ( empty( $type ) ) {
			$type = $this->default_message_type;

			if ( is_array( $data ) && isset( $data['type'] ) ) {
				$type = $data['type'];
			} else if ( is_wp_error( $data ) ) {
				$type = $data->get_error_code();
			}
		}

		$request_uri = ( ! empty( $_SERVER['REQUEST_URI'] ) ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : 'Unknown';

		// Construct the message.
		$message = array(
			'type'    => $type,
			'time'    => time(),
			'user_ID' => get_current_user_id(),
			'uri'     => preg_replace( '/code=([^&]+)/i', 'code=', $request_uri ),
			'data'    => $data,
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
			$logs = array_slice( $logs, ( $items_to_remove * -1 ) );
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
		return update_option( $this->option_name, $logs, false );
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
			<th class="col-details">Details</th>
			<th class="col-data">Data</th>
			</thead>
			<tbody>
			<?php foreach ( $logs as $log ) { ?>
				<tr>
					<td class="col-details">
						<div>
							<label><?php esc_html_e( 'Type', 'hello-login' ); ?>: </label>
							<?php print esc_html( $log['type'] ); ?>
						</div>
						<div>
							<label><?php esc_html_e( 'Date', 'hello-login' ); ?>: </label>
							<?php print esc_html( gmdate( 'Y-m-d H:i:s', $log['time'] ) ); ?>
						</div>
						<div>
							<label><?php esc_html_e( 'User', 'hello-login' ); ?>: </label>
							<?php print esc_html( ( get_userdata( $log['user_ID'] ) ) ? get_userdata( $log['user_ID'] )->user_login : '0' ); ?>
						</div>
						<div>
							<label><?php esc_html_e( 'URI ', 'hello-login' ); ?>: </label>
							<?php print esc_url( $log['uri'] ); ?>
						</div>
					</td>

					<td class="col-data"><pre><?php var_dump( $log['data'] ); ?></pre></td>
				</tr>
			<?php } ?>
			</tbody>
		</table>
		<?php
		$output = ob_get_clean();

		return $output;
	}
}
