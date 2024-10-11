<?php

class METERMAID_SMS {
	static $debug = false;

	public static function is_configured() {
		if (
			   empty( get_option( 'METERMAID_TWILIO_ACCOUNT_SID' ) )
			|| empty( get_option( 'METERMAID_TWILIO_AUTH_TOKEN' ) )
			|| empty( get_option( 'METERMAID_TWILIO_MESSAGING_SERVICE_SID' ) )
		) {
			return false;
		}

		return true;
	}

	public static function init() {
		global $wpdb;

		foreach ( $_POST as $key => $val ) {
			if ( strpos( $key, "metermaid_sms_" ) === 0 ) {
				$_POST[ $key ] = stripslashes_deep( $val );
			}
		}

		if ( isset( $_GET['metermaid_sms_received'] ) ) {
			$_POST = stripslashes_deep( $_POST );

			METERMAID_SMS::confirm_twilio_request_or_die();

			METERMAID_SMS::respond_to_message();
			die;
		}

		if ( isset( $_GET['metermaid_call_received'] ) ) {
			$_POST = stripslashes_deep( $_POST );

			METERMAID_SMS::confirm_twilio_request_or_die();

			METERMAID_SMS::respond_to_call();
			die;
		}

		if ( isset( $_POST['metermaid_sms_send_message_to_number'] ) ) {
			$number = METERMAID_SMS::standardize_phone_number( $_POST['metermaid_sms_phone_number'] );

			if ( ! wp_verify_nonce( $_POST['nonce'], 'metermaid-sms-send-message-to-' . $number ) ) {
				echo 'You are not authorized to do this.';
				wp_die();
			}

			METERMAID_SMS::send_message( $number, $_POST['metermaid_sms_message'] );

			wp_redirect( add_query_arg( array( 'metermaid_sms_message_sent' => 1 ), $_SERVER['REQUEST_URI'] ) );
			die;
		}

		add_action( 'admin_menu', array( __CLASS__, 'add_options_menu' ) );
	}

	public static function add_options_menu() {
		add_submenu_page(
			'metermaid',
			__( 'Metermaid SMS', 'metermaid' ),
			__( 'Manage SMS', 'metermaid' ),
			'metermaid-manage-sms',
			'metermaid-sms',
			array( 'METERMAID_SMS', 'admin_page' )
		);
	}

	public static function confirm_twilio_request_or_die() {
		if ( ! METERMAID_SMS::is_configured() ) {
			die( "SMS handling is not configured." );
		}

		if ( ! isset( $_SERVER["HTTP_X_TWILIO_SIGNATURE"] ) ) {
			die( "Request did not come from Twilio." );
		}

		require_once __DIR__ . '/twilio-php-main/src/Twilio/autoload.php';

		$validator = new Twilio\Security\RequestValidator( get_option( 'METERMAID_TWILIO_AUTH_TOKEN' ) );

		if ( ! $validator->validate( $_SERVER["HTTP_X_TWILIO_SIGNATURE"], get_site_url() . $_SERVER['REQUEST_URI'], $_POST ) ) {
			error_log( "confirm_twilio_request_or_die: " . print_r( array( 'SERVER' => $_SERVER, 'POST' => $_POST ), true ) );

			if ( get_option( 'admin_email' ) ) {
				wp_mail( get_option( 'admin_email' ), "Request did not come from Twilio", print_r( array( 'SERVER' => $_SERVER, 'POST' => $_POST ), true ) );
			}

			die( "Could not confirm request came from Twilio." );
		}
	}

	public static function admin_page() {
		global $wpdb;

		?>
		<div class="wrap">
			<?php if ( isset( $_GET['metermaid_sms_message_sent'] ) ) { ?>
				<div class="notice notice-success"><p>Message sent.</p></div>
			<?php } ?>

			<h1 class="wp-heading-inline">
				<a href="<?php echo esc_url( remove_query_arg( array( 'metermaid_system_id', 'metermaid_meter_id' ) ) ); ?>"><?php echo esc_html( __( 'Metermaid', 'metermaid' ) ); ?></a>
				&raquo;
				<?php echo esc_html( __( 'SMS', 'metermaid' ) ); ?>
			</h1>
			<?php

			if ( ! METERMAID_SMS::is_configured() ) {
				?>
				<div class="metermaid-card card">
					<p><a href="<?php echo esc_attr( site_url( 'wp-admin/admin.php?page=metermaid-home#tab-edit-settings' ) ); ?>"><?php echo esc_html( __( 'Enter your Twilio account settings on the main Metermaid dashboard in order to enable the SMS features.', 'metermaid' ) ); ?></a></p>
				</div>
				<?php
			}

			$phone_number = ! empty( $_GET['metermaid_sms_number'] ) ? METERMAID_SMS::standardize_phone_number( $_GET['metermaid_sms_number'] ) : false;

			if ( $phone_number ) {
				?>
				<div class="metermaid-tabbed-content-container">
					<nav class="nav-tab-wrapper">
						<a href="#tab-send-sms" class="nav-tab" data-metermaid-tab="send-sms"><?php echo esc_html( __( 'Send SMS', 'metermaid' ) ); ?></a>
					</nav>
					<div class="metermaid-tabbed-content card">
						<div data-metermaid-tab="send-sms">
							<?php

							if ( METERMAID_SMS::is_configured() ) {
								?>
								<form method="post" action="">
									<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'metermaid-sms-send-message-to-' . $phone_number ) ); ?>" />

									<input type="hidden" name="metermaid_sms_send_message_to_number" value="1" />

									<input type="hidden" name="metermaid_sms_phone_number" value="<?php echo esc_attr( $phone_number ); ?>" />
									<textarea name="metermaid_sms_message" rows="5" cols="80" style="font-family: monospace;"></textarea>
									<p><input type="submit" value="Send Text to <?php echo esc_attr( METERMAID_SMS::readable_phone_number( $phone_number ) ); ?>" class="button-primary" /> <span class="metermaid-sms-message-length"></span></p>
								</form>
								<?php
							}

							?>
						</div>
					</div>
				</div>
				<?php

				echo '<h2 class="wp-heading-inline">SMS History with ' . METERMAID_SMS::readable_phone_number( $phone_number ) . "</h2>";

				$messages = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . $wpdb->prefix . "metermaid_sms_conversations WHERE number=%s ORDER BY timestamp DESC", $phone_number ) );

			}
			else {
				echo '<h2>Recent SMS Messages</h2>';

				$messages = $wpdb->get_results( "SELECT * FROM " . $wpdb->prefix . "metermaid_sms_conversations ORDER BY timestamp DESC LIMIT 500" );
			}

			?>
			<table class="wp-list-table widefat striped">
				<thead>
					<tr>
						<th>Timestamp</th>
						<th>Number</th>
						<th></th>
						<th>Message</th>
					</tr>
				</thead>
				<tbody>
					<?php

					foreach ( $messages as $message ) {
						?>
						<tr>
							<td><?php echo METERMAID::local_timestamp( $message->timestamp, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ); ?></td>
							<td style="white-space: nowrap;">
								<a href="<?php echo esc_url( add_query_arg( array( 'metermaid_sms_history' => 1, 'metermaid_sms_number' => $message->number ) ) ); ?>">
									<?php echo esc_html( METERMAID_SMS::readable_phone_number( $message->number ) ); ?>
								</a>
							</td>
							<td>
								<?php if ( $message->to_or_from == 'to' ) { ?>
									received:
								<?php } else { ?>
									sent:
								<?php } ?>

								<?php if ( $message->status == 'pending' ) { ?>
									(pending)
								<?php } ?>
							</td>
							<td><p><?php echo esc_html( $message->message ); ?></p></td>
						<?php
					}

					?>
				</tbody>
				<tfoot>
					<tr>
						<th>Timestamp</th>
						<th>Number</th>
						<th></th>
						<th>Message</th>
					</tr>
				</tfoot>
			</table>
		</div>
		<?php
	}

	public static function db_setup() {
		global $wpdb;

		$wpdb->query( "CREATE TABLE IF NOT EXISTS " . $wpdb->prefix . "metermaid_sms_conversations (
			metermaid_sms_conversation_id bigint NOT NULL AUTO_INCREMENT,
			number char(12) DEFAULT NULL,
			timestamp datetime NOT NULL,
			to_or_from varchar(4) DEFAULT NULL,
			message varchar(1000) DEFAULT NULL,
			status varchar(32) DEFAULT NULL,
			PRIMARY KEY (metermaid_sms_conversation_id),
			KEY timestamp (timestamp),
			KEY number (number)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4" );

		$wpdb->query( "CREATE TABLE IF NOT EXISTS " . $wpdb->prefix . "metermaid_sms_log (
			metermaid_sms_log_id bigint NOT NULL AUTO_INCREMENT,
			timestamp datetime NOT NULL,
			log text,
			PRIMARY KEY (metermaid_sms_log_id),
			KEY timestamp (timestamp)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4" );
	}

	public static function send_message( $number, $message ) {
		global $wpdb;

		error_log( "SMS send_message: " . print_r( compact( 'number', 'message' ), true ) );

		$number = METERMAID_SMS::standardize_phone_number( $number );

		if ( ! $number ) {
			error_log( "Tried to send message to empty number: " . print_r( func_get_args(), true ) );

			return false;
		}

		$wpdb->query( $wpdb->prepare( "INSERT INTO " . $wpdb->prefix . "metermaid_sms_conversations SET number=%s, timestamp=%s, to_or_from='to', message=%s", $number, METERMAID::UTC_NOW(), wp_encode_emoji( $message ) ) );

		if ( METERMAID_SMS::$debug ) {
			return;
		}

		require_once __DIR__ . '/twilio-php-main/src/Twilio/autoload.php';

		$from = get_option( 'METERMAID_TWILIO_MESSAGING_SERVICE_SID' );

		$client = new Twilio\Rest\Client( get_option( 'METERMAID_TWILIO_ACCOUNT_SID' ), get_option( 'METERMAID_TWILIO_AUTH_TOKEN' ) );
		$client->messages->create(
			$number,
			array(
				'from' => $from,
				'body' => $message,
			)
		);
	}

	public static function respond_to_message() {
		global $wpdb;

		error_log( "SMS respond_to_message: " . print_r( array( 'GET' => $_GET, 'POST' => $_POST ), true ) );
		$wpdb->query( $wpdb->prepare( "INSERT INTO ".$wpdb->prefix."metermaid_sms_log SET timestamp=%s, log=%s", METERMAID::UTC_NOW(), wp_encode_emoji( serialize( array( 'GET' => $_GET, 'SERVER' => $_SERVER, 'POST' => $_POST ) ) ) ) );

		$message = trim( $_POST['Body'] );
		$number = $_POST['From'];

		$wpdb->query( $wpdb->prepare( "INSERT INTO " . $wpdb->prefix . "metermaid_sms_conversations SET number=%s, timestamp=%s, to_or_from='from', message=%s", $number, METERMAID::UTC_NOW(), wp_encode_emoji( $message ) ) );

		$email_subject = "SMS received from " . $number;

		$email_body = $message;
		$email_body .= "\n\nView conversation or respond at https://water.efinke.com/wp-admin/admin.php?page=metermaid-sms&metermaid_sms_history=1&metermaid_sms_number=" . urlencode( $number );

		wp_mail( get_option( 'admin_email' ), $email_subject, $email_body );

		require_once __DIR__ . '/twilio-php-main/src/Twilio/autoload.php';

		header( "Content-type: text/xml" );

		if ( strtolower( $message ) == 'unsubscribe' || strtolower( $message ) == 'stop' ) {
			$response = new Twilio\TwiML\MessagingResponse();
			$response->message(
				"You have been unsubscribed and will no longer receive text messages from Metermaid. To resubscribe, reply with 'subscribe'"
			);
			echo $response;
			die;
		} else if ( strtolower( $message ) == 'subscribe' ) {
			// Check if they're opting in.
			$response = new Twilio\TwiML\MessagingResponse();
			$response->message(
				"You have been subscribed to Metermaid text messages. To unsubscribe, reply with 'unsubscribe'"
			);
			echo $response;
			die;
		} else {
			if ( preg_match( '/^\s*[0-9,]+\s*$/', $message ) ) {
				// It's a reading.
				$reading_value = preg_replace( '/[^0-9]/', '', $message );

				$potential_users = get_users( array(
					'meta_key' => 'metermaid_phone_number',
					'meta_value' => METERMAID_SMS::standardize_phone_number( $number ),
				) );

				$users = array();

				foreach ( $potential_users as $user ) {
					if ( get_user_meta( $user->ID, 'metermaid_meter_id', true ) ) {
						$users[] = $user;
					}
				}

				$message = '';

				if ( empty( $users ) ) {
					$message = "This phone number hasn't registered for Metermaid. Visit metermaid.org today to start logging your water meter readings.";
				} else if ( count( $users ) > 1 ) {
					// @todo Check if all the users have a chosen meter. If only one does, use that.
					$message = "This phone number is assigned to multiple Metermaid users; please email help@metermaid.org to get this fixed.";
				} else {
					$meter_id = get_user_meta( $users[0]->ID, 'metermaid_meter_id', true );
					$meter = new METERMAID_METER( $meter_id );
					$meter->add_reading( $reading_value, METERMAID::local_timestamp( date( "Y-m-d" ), "Y-m-d" ), $users[0]->ID );

					$message = "Thanks! Your meter reading has been recorded.";
				}

				if ( $message ) {
					$response = new Twilio\TwiML\MessagingResponse();
					$response->message( $message );

					echo $response;
				}
			}
		}

		die;
	}

	public static function respond_to_call() {
		global $wpdb;

		error_log( "SMS respond_to_call: " . print_r( array( 'GET' => $_GET, 'POST' => $_POST ), true ) );
		$wpdb->query( $wpdb->prepare( "INSERT INTO ".$wpdb->prefix."metermaid_sms_log SET timestamp=%s, log=%s", METERMAID::UTC_NOW(), serialize( array( 'GET' => $_GET, 'SERVER' => $_SERVER, 'POST' => $_POST ) ) ) );

		$number = $_POST['From'] ?? ( $_GET['From'] ?? 'Unknown' );

		$wpdb->query( $wpdb->prepare( "INSERT INTO ".$wpdb->prefix."metermaid_sms_conversations SET number=%s, timestamp=%s, to_or_from='from', message='[Voice Call]'", $number, METERMAID::UTC_NOW() ) );

		require_once __DIR__ . '/twilio-php-main/src/Twilio/autoload.php';

		header( "Content-type: text/xml" );

		$response = new Twilio\TwiML\VoiceResponse();
		$response->say( "This number does not accept incoming calls. Please send a text or visit our website at Meter Maid dot org.", array( 'voice' => 'alice' ) );
		echo $response;

		die;
	}

	public static function standardize_phone_number( $number ) {
		$number = preg_replace( '/[^0-9]/', '', $number );
		$number = preg_replace( '/^1/', '', $number );

		if ( strlen( $number ) != 10 ) {
			return '';
		}

		return '+1' . $number;
	}

	public static function readable_phone_number( $number ) {
		$sanitized = preg_replace( '/^1/', '', preg_replace( '/[^0-9]/', '', trim( $number ) ) );

		if ( $sanitized ) {
			return substr( $sanitized, 0, 3 ) . '-' . substr( $sanitized, 3, 3 ) . '-' . substr( $sanitized, 6, 4 );
		}

		return '';
	}
}

add_action( 'init', array( 'METERMAID_SMS', 'init' ), 20 );
add_action( 'plugins_loaded', array( 'METERMAID_SMS', 'db_setup' ) );