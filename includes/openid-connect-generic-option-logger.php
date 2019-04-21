<?php
/**
 * Simple class for logging messages to the options table
 */
class OpenID_Connect_Generic_Option_Logger {
	
	// wp option name/key
	private $option_name;
	
	// default message type
	private $default_message_type;
	
	// the number of items to keep in the log
	private $log_limit;
	
	// whether or not the 
	private $logging_enabled;
	
	// internal cache of logs
	private $logs;

	/**
	 * Setup the logger according to the needs of the instance
	 * 
	 * @param string $option_name
	 * @param string $default_message_type
	 * @param bool|TRUE $logging_enabled
	 * @param int $log_limit
	 */
	function __construct( $option_name, $default_message_type = 'none', $logging_enabled = true, $log_limit = 1000 ){
		$this->option_name = $option_name;
		$this->default_message_type = $default_message_type;
		$this->logging_enabled = (bool) $logging_enabled;
		$this->log_limit = (int) $log_limit;
	}

	/**
	 * Subscribe logger to a set of filters
	 * 
	 * @param $filter_names
	 * @param int $priority
	 */
	function log_filters( $filter_names, $priority = 10 ){
		if ( ! is_array( $filter_names ) ) {
			$filter_names = array( $filter_names );
		}
		
		foreach ( $filter_names as $filter ){
			add_filter( $filter, array( $this, 'log_hook' ), $priority );
		}
	}

	/**
	 * Subscribe logger to a set of actions
	 * 
	 * @param $action_names
	 * @param $priority
	 */
	function log_actions( $action_names, $priority ){
		if ( ! is_array( $action_names ) ) {
			$action_names = array( $action_names );
		}

		foreach ( $action_names as $action ){
			add_filter( $action, array( $this, 'log_hook' ), $priority );
		}
	}

	/**
	 * Log the data 
	 * 
	 * @param null $arg1
	 * @return null
	 */
	function log_hook( $arg1 = null ){
		$this->log( func_get_args(), current_filter() );
		return $arg1;
	}
	
	/**
	 * Save an array of data to the logs
	 * 
	 * @param $data mixed
	 * @return bool
	 */
	public function log( $data, $type = null ) {
		if ( (bool) $this->logging_enabled ) {
			$logs = $this->get_logs();
			$logs[] = $this->make_message( $data, $type );
			$logs = $this->upkeep_logs( $logs );
			return $this->save_logs( $logs );
		}
		
		return false;
	}

	/**
	 * Retrieve all log messages
	 * 
	 * @return array
	 */
	public function get_logs() {
		if ( is_null( $this->logs ) ) {
			$this->logs = get_option( $this->option_name, array() );
		}

		return $this->logs;
	}

	/**
	 * Get the name of the option where this log is stored
	 * 
	 * @return string
	 */
	public function get_option_name(){
		return $this->option_name;
	}

	/**
	 * Create a message array containing the data and other information
	 *
	 * @param $data mixed
	 * @param $type
	 *
	 * @return array
	 */
	private function make_message( $data, $type ){
		// determine the type of message
		if ( empty( $type ) ) {
			$this->default_message_type;
			
			if ( is_array( $data ) && isset( $data['type'] ) ){
				$type = $data['type'];
			}
			else if ( is_wp_error( $data ) ){
				$type = $data->get_error_code();
			}
		}

		// construct our message
		$message = array(
			'type'    => $type,
			'time'    => time(),
			'user_ID' => get_current_user_id(),
			'uri'     => $_SERVER['REQUEST_URI'],
			'data'    => $data,
		);

		return $message;
	}
	
	/**
	 * Keep our log count under the limit
	 *
	 * @param $message array - extra data about the message
	 * @return array
	 */
	private function upkeep_logs( $logs ) {
		$items_to_remove = count( $logs ) - $this->log_limit;

		if ( $items_to_remove > 0 ){
			// keep only the last $log_limit messages from the end
			$logs = array_slice( $logs, ( $items_to_remove * -1) );
		} 
		
		return $logs;
	}

	/**
	 * Save the log messages
	 * 
	 * @param $logs
	 * @return bool
	 */
	private function save_logs( $logs ){
		// save our logs
		$this->logs = $logs;
		return update_option( $this->option_name, $logs, false );
	}

	/**
	 * Clear all log messages
	 */
	public function clear_logs(){
		$this->save_logs( array() );
	}

	/**
	 * Get a simple html table of all the logs
	 * 
	 * @param array $logs
	 * @return string
	 */
	public function get_logs_table( $logs = array() ){
		if ( empty( $logs ) ) {
			$logs = $this->get_logs();
		}
		$logs = array_reverse( $logs );
		
		ini_set( 'xdebug.var_display_max_depth', -1 );
		
		ob_start();
		?>
		<style type="text/css">
			#logger-table .col-data { width: 85% }
			#logger-table .col-details div { padding: 4px 0; border-bottom: 1px solid #bbb; }
			#logger-table .col-details label { font-weight: bold; }
		</style>
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
							<label><?php _e( 'Type' ); ?>: </label>
							<?php print $log['type']; ?>
						</div>
						<div>
							<label><?php _e( 'Date' ); ?>: </label>
							<?php print date( 'Y-m-d H:i:s', $log['time'] ); ?>
						</div>
						<div>
							<label><?php _e( 'User' ); ?>: </label>
							<?php print ( get_userdata( $log['user_ID'] ) ) ? get_userdata( $log['user_ID'] )->user_login : '0'; ?>
						</div>
						<div>
							<label><?php _e( 'URI ' ); ?>: </label>
							<?php print $log['uri']; ?>
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
