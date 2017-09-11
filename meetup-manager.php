<?php

/**
 * Plugin Name: Meetup
 */

include_once( 'vendor/user3581488/meetup/meetup.php' );
include_once( 'api.php' );

add_action( 'admin_menu', function() {
	add_menu_page( 'Meetup', 'Meetup', 'manage_options', 'meetup', 'meetup_render_menu' );
});

function meetup_render_menu() {
	$last_event = meetup_get_last_event();
	$events = meetup_get_events();

	$event_id = isset( $_GET['event_id'] ) ? absint( $_GET['event_id'] ) : false;

	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		
		<?php if ( ! $event_id ): ?>
			<h3>Last Event</h3>
			<p><a href="<?php echo esc_url( add_query_arg( 'event_id', $last_event->id ) ); ?>"><?php echo $last_event->name; ?></a></p>

			<h3>Next Events</h3>
			<ul>
				<?php foreach ( $events as $event ): ?>
					<li><a href="<?php echo esc_url( add_query_arg( 'event_id', $event->id ) ); ?>"><?php echo $event->name; ?></a></li>
				<?php endforeach; ?>
			</ul>
		<?php else: ?>
			<?php $event = meetup_get_event( $event_id ); ?>
			<?php $attendants = meetup_get_event_attendants( $event_id ); ?>
			<h4>Attendants</h4>
			<ul>
				<?php foreach ( $attendants as $attendant ): ?>
					<li>
						<?php echo $attendant->member->name ?> (<?php echo $attendant->member->id ?>)
						<select name="" id="" class="member-attended" data-member="<?php echo $attendant->member->id; ?>" data-event="<?php echo $last_event->id ?>">
							<option value="no">No</option>
							<option <?php selected( meetup_member_attended( $attendant->member->id, $event->id ) ) ?> value="yes">Yes</option>
						</select>
					</li>

				<?php endforeach; ?>
			</ul>

			<script>
                jQuery( '.member-attended' ).change( function( e ) {
                    var value = jQuery( this ).val();
                    var data = {
                        action: 'meetup_set_attendance',
                        member_id: jQuery(this).data('member'),
                        event_id: jQuery(this).data('event'),
                        attended: ( 'yes' === value ) ? 1 : 0
                    };

                    jQuery.post( ajaxurl, data, function() {
                        console.log("DONE");
                    });
                })
			</script>
		<?php endif; ?>
	</div>
	<?php
}

add_action( 'wp_ajax_meetup_set_attendance', function() {
	$value = (bool)$_POST['attended'];
	$member_id = absint( $_POST['member_id'] );
	$event_id = absint( $_POST['event_id'] );

	meetup_set_member_attendance( $member_id, $event_id, $value );
});

function meetup_get_last_event() {
	$meetup = new Meetup_Manager_API( array( 'key' => MEETUP_API_KEY ) );
	try {
		$event = $meetup->getEvents( array( 'urlname' => MEETUP_URL_NAME, 'scroll' => 'recent_past', 'page' => 1 ) );
	}
	catch ( Exception $e ) {
		return false;
	}

	return $event[0];
}

function meetup_get_events() {
	$meetup = new Meetup_Manager_API( array( 'key' => MEETUP_API_KEY ) );
	try {
		$events = $meetup->getEvents( array( 'urlname' => MEETUP_URL_NAME, 'page' => 3 ) );
	}
	catch ( Exception $e ) {
		return false;
	}

	return $events;
}


function meetup_get_member( $member_id ) {
	$meetup = new Meetup_Manager_API( array( 'key' => MEETUP_API_KEY ) );
	try {
		$member = $meetup->getMember( array( 'id' => $member_id ) );
	}
	catch ( Exception $e ) {
		return false;
	}

	return $member;
}

function meetup_get_event( $event_id ) {
	$meetup = new Meetup_Manager_API( array( 'key' => MEETUP_API_KEY ) );
	try {
		$event = $meetup->getEvent( array( 'urlname' => MEETUP_URL_NAME, 'id' => $event_id ) );
	}
	catch ( Exception $e ) {
		return false;
	}

	return $event;
}

function meetup_get_event_attendants( $event_id ) {
	$meetup = new Meetup_Manager_API( array( 'key' => MEETUP_API_KEY ) );
	try {
		$member = $meetup->getEventAttendants( array( 'urlname' => MEETUP_URL_NAME, 'id' => $event_id ) );
	}
	catch ( Exception $e ) {
		return false;
	}

	return $member;
}

function meetup_get_event_attendance( $event_id ) {
	global $wpdb;
	$table = meetup_get_table();

	return $wpdb->get_results( $wpdb->prepare( "SELECT member_id FROM $table WHERE event_id = %d AND blog_id = %d", $event_id, get_current_blog_id() ) );
}

function meetup_set_member_attendance( $member_id, $event_id, $attended = false ) {
	global $wpdb;

	$table = meetup_get_table();

	if ( ! $attended ) {
		$wpdb->delete(
			$table,
			array(
				'member_id' => $member_id,
				'event_id' => $event_id,
				'blog_id' => get_current_blog_id(),
			),
			array( '%d', '%d', '%d' )
		);
	}
	elseif ( ! meetup_member_attended( $member_id, $event_id ) ) {
		$wpdb->insert(
			$table,
			array(
				'member_id' => $member_id,
				'event_id' => $event_id,
				'blog_id' => get_current_blog_id(),
			),
			array( '%d', '%d', '%d', '%s' )
		);
	}
}

function meetup_member_attended( $member_id, $event_id ) {
	global $wpdb;

	$table = meetup_get_table();

	// Cache this thing
	$all = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM $table WHERE event_id = %d AND blog_id = %d",
			$event_id,
			get_current_blog_id()
		)
	);

	if ( wp_list_filter( $all, array( 'member_id' => $member_id ) ) ) {
		return true;
	}

	return false;
}

function meetup_get_table() {
	global $wpdb;
	return $wpdb->base_prefix . 'meetup_attendance';
}


function meetup_activate() {
	global $wpdb;

	$table = $wpdb->base_prefix . 'meetup_attendance';

	$charset_collate = $wpdb->get_charset_collate();

	$query = "CREATE TABLE $table (
  blog_id bigint(20) NOT NULL default '1',
  event_id bigint(20) unsigned NOT NULL,
  member_id bigint(20) unsigned NOT NULL,
  PRIMARY KEY  (event_id,member_id),
  KEY blog_id (blog_id)
) $charset_collate;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

	dbDelta( $query );
}

register_activation_hook( __FILE__, 'meetup_activate' );