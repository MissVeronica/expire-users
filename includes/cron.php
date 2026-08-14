<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class Expire_Users_Cron {

	public function __construct() {
		add_action( 'expire_user_cron', array( $this, 'do_cron' ) );
	}

	/**
	 * Do the scheduler
	 */
	function do_cron() {

		global $um_expire_users;

		if ( get_option( 'expire_users_default_expire_settings' ) !== false ) {

			$maybe_expire_users = Expire_Users_Query::query( array(
				'expired'              => false,
				'expired_date'         => current_time( 'timestamp' ),
				'expired_date_compare' => '<'
			) );

			$um_expired_users = $maybe_expire_users->results;
			$um_expired_users = $um_expire_users->um_expire_users_validation( $um_expired_users );

			if ( count( $um_expired_users ) > 0 ) {

				foreach ( $um_expired_users as $expired_user ) {

					$this_expire_user = new Expire_User( $expired_user->ID );
					$this_expire_user->expire();
				}
			}

			$um_expire_users->um_expired_users_send_reminders();
			$recurrence = $um_expire_users->cron_schedule_update_recurrence();
		}

		if ( wp_next_scheduled( 'expire_user_cron' ) ) {
			wp_clear_scheduled_hook( 'expire_user_cron' );
		}

		if ( ! isset( $recurrence['interval'] ) || 
		     ! isset( $recurrence['display'] ) ||
			 ! wp_schedule_event( $recurrence['interval'], $recurrence['display'], 'expire_user_cron' ) ) {

			$date = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ));
			wp_schedule_event( $date->getTimestamp() + HOUR_IN_SECONDS, 'hourly', 'expire_user_cron' );
		}
	}
}
