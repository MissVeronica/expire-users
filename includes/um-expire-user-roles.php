<?php

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! class_exists( 'UM' ) ) return;
if ( ! class_exists( 'Expire_Users' ) ) return;


class UM_Expire_Users {

    public $slugs                = array();
    public $role_meta            = array();
    public $expire_user_settings = array();
    public $role_meta_cache_slug = array();
    public $role_meta_cache_uid  = array();
    public $user_status_cache    = array();

    public $current_role_id        = false;
    public $priority_role_id       = false;

    public $cron_hook              = 'expire_user_cron';
    public $cron_um_version        = 'cron um version 1.0.0';

    public $date_time_format       = 'F j, Y  H:i';
    public $time_date_format       = 'H:i F j';
    public $date_format            = 'M d, Y';
    public $time_format            = 'H:i';

    public $role_exclude_setting = '';

    public $templates = array(  'welcome'    => 'expire_users_welcome_email',
                                'reminder' => 'expire_users_reminder_email',
                                'user'     => 'expire_users_user_email',
                                'admin'    => 'expire_users_admin_email',
                                'renewal'  => 'expire_users_renewal_email',
                            );

    public $expire_users_metakeys = array(
                                            '_expire_user_date',
                                            '_expire_user_expired',
                                            '_expire_user_settings',
                                            '_expire_users_role',
                                            'expire_users_reminder',
                                            '_expire_users_reminder',
                                            'expire_users',
                                        );

    function __construct() {

        global $expire_users;

        add_action( 'um_after_register_fields',       array( $this, 'add_a_hidden_field_to_register_form' ), 10, 1 );
        add_filter( 'um_registration_set_extra_data', array( $this, 'registration_set_extra_data' ), 10, 3 );
        add_action( 'um_after_login_fields',          array( $this, 'add_a_hidden_field_to_login_form' ), 10, 1 );

        add_filter( 'um_account_page_default_tabs_hook',    array( $this, 'custom_account_tab' ), 100 );
        add_filter( 'um_account_content_hook_expire_users', array( $this, 'account_content_hook_expire_users' ), 10, 2 );
        add_action( 'um_after_user_account_updated',        array( $this, 'customtab_expire_users' ), 100, 2 );
        add_action( 'um_on_login_before_redirect',          array( $this, 'login_update_expire_users' ), 10, 1 );
        add_filter( 'um_predefined_fields_hook',            array( $this, 'predefined_fields_expire_users' ), 10, 1 );
        add_filter( 'um_email_notifications',               array( $this, 'email_notification_expire_users' ), 99 );

        add_action( 'expire_users_expired',              array( $this, 'expire_user_email' ), 20, 1 );
        add_filter( 'um_admin_role_metaboxes',           array( $this, 'admin_role_metaboxes_expire_users' ), 10, 1 );
        add_action( 'load-toplevel_page_ultimatemember', array( $this, 'load_metabox_expire_users' ) );

        add_action( 'manage_users_extra_tablenav', array( $this, 'render_custom_filter_options' ));
        add_action( 'pre_get_users',               array( $this, 'filter_expire_users' ));
        add_filter( 'user_row_actions',            array( $this, 'user_row_actions' ), 20, 2 );
        add_action( 'admin_init',                  array( $this, 'renew_user_now' ) );

        add_filter( 'plugin_action_links_' . Basename_EUR, array( $this, 'user_roles_settings_link' ), 10, 1 );

        if ( ! empty( get_option( 'date_format' ) ) && ! empty( get_option( 'time_format' ) )) {
            $this->date_time_format = sprintf( '%s %s', get_option( 'date_format' ), get_option( 'time_format' ));
            $this->time_date_format = sprintf( '%s %s', get_option( 'time_format' ), get_option( 'date_format' ));
        }
        $this->date_format = ! empty( get_option( 'date_format' )) ? get_option( 'date_format' ) : $this->date_format;
        $this->time_format = ! empty( get_option( 'time_format' )) ? get_option( 'time_format' ) : $this->time_format;
    }

    public function user_roles_settings_link( $links ) {

        $url = get_admin_url() . 'users.php?page=expire_users';
        $links[] = '<a href="' . esc_url( $url ) . '" title="' . esc_html__( 'Plugin basic settings', 'expire-users' ) . '">' . __( 'Expire Users', 'expire-users' ) . '</a>';
        
        $url = get_admin_url() . 'admin.php?page=um_roles';
        $links[] = '<a href="' . esc_url( $url ) . '" title="' . esc_html__( 'Additional settings per User Role', 'expire-users' ) . '">' . __( 'Expire User Roles', 'expire-users' ) . '</a>';
        
        return $links;
    }

    public function custom_account_tab( $tabs ) {

        if ( $this->user_expire_status( um_user( 'ID' )) == 'active' ) {

            $tabs[800]['expire_users']['icon']         = 'far fa-clock';
            $tabs[800]['expire_users']['title']        = esc_html__( 'User Role Expiration', 'expire-users' );
            $tabs[800]['expire_users']['submit_title'] = esc_html__( 'Update User Role',  'expire-users' );
            $tabs[800]['expire_users']['custom']       = true;
        }

        if ( $this->user_expire_status( um_user( 'ID' )) == 'expired' ) {

            $back_role = um_user( '_expire_users_role' );
            $back_role = ! isset( wp_roles()->get_names()[$back_role] ) ? '' : wp_roles()->get_names()[$back_role];

            $tabs[800]['expire_users']['icon']         = 'far fa-clock';
            $tabs[800]['expire_users']['title']        = esc_html__( 'User Role Expired', 'expire-users' );
            $tabs[800]['expire_users']['submit_title'] = sprintf( esc_html__( 'Renew User Role %s',  'expire-users' ), $back_role  );
            $tabs[800]['expire_users']['custom']       = true;
        }

        return $tabs;
    }

    public function user_expire_status( $user_id ) {

        if ( $this->get_default_expire_settings()) {
            if ( ! empty( $user_id )) {

                if ( isset( $this->user_status_cache[$user_id] )) {
                    return $this->user_status_cache[$user_id];
                }

                um_fetch_user( $user_id );
                $this->get_user_role_meta( $user_id );
                if ( isset( $this->role_meta['_expire_users_role_selected'] ) && $this->role_meta['_expire_users_role_selected'] == 1 ) {
                    switch ( um_user( '_expire_user_expired' )) {
                        case 'Y':   $this->user_status_cache[$user_id] = 'expired';  break;
                        case 'N':   $this->user_status_cache[$user_id] = 'active';   break;
                        default:    $this->user_status_cache[$user_id] = 'inactive'; break;
                    }
                    return $this->user_status_cache[$user_id];
                }
            }
        }
        return false;
    }

    public function get_user_role_meta( $user_id ) {

        $this->current_role_id = false;
        $role_id = UM()->roles()->get_priority_user_role( $user_id );
        
        if ( ! empty( $role_id )) {

            $this->priority_role_id = $role_id;
            $this->get_default_expire_settings();

            if ( isset( $this->expire_user_settings['expire_user_role']) && $this->expire_user_settings['expire_user_role'] == $role_id ) {
                $role_id = get_user_meta( $user_id, '_expire_users_role', true );
            }

            if ( $this->current_role_id == $role_id ) {
                return;
            }

            $slug = $this->get_role_slug( $role_id );
            $this->role_meta = get_option( "um_role_{$slug}_meta", false );

            if ( UM()->options()->get( $this->templates['reminder'] . '_on' ) != 1 ) {
                $this->role_meta['_expire_users_reminder'] = 0;
                $this->role_meta['_expire_users_reminder_days'] = '';
            }
        }

        $this->current_role_id = $role_id;
    }

    public function get_role_slug( $role_id = '' ) {

        $slug = $role_id;
        if ( ! empty( $role_id )) {
            if ( strpos( $slug, 'um_' ) === 0 ) {
                $slug = substr( $slug, 3 );
            }
        }
        return $slug;
    }

    public function get_default_expire_settings() {

        if ( empty( $this->expire_user_settings )) {

            $settings = get_option( 'expire_users_default_expire_settings' );
            $this->expire_user_settings['plugin_configured'] = false;
            if ( $settings !== false && is_array( $settings )) {

                $this->expire_user_settings = array_merge( $settings, $this->expire_user_settings );
                if ( isset( $this->expire_user_settings['expire_user_role'] )) {
                    $this->role_exclude_setting = wp_roles()->get_names()[$this->expire_user_settings['expire_user_role']];
                }

                if ( isset( $this->expire_user_settings['auto_expire_registered_users']) && $this->expire_user_settings['auto_expire_registered_users'] == 'Y' ) {
                    if ( isset( $this->expire_user_settings['expire_user_role']) && ! empty( $this->expire_user_settings['expire_user_role'])) {
                        $this->expire_user_settings['plugin_configured'] = true;
                    }
                }
            }
        }
        return $this->expire_user_settings['plugin_configured'];
    }

    public function predefined_fields_expire_users( $predefined_fields ) {

        if ( $this->user_expire_status( um_user( 'ID' )) !== false ) {

            if ( isset( $this->expire_user_settings['expire_user_date_in_block'] ) && 
                 isset( $this->expire_user_settings['expire_user_date_in_num'] ) && 
               ! empty( $this->expire_user_settings['expire_user_date_in_num'] )) {

                if ( intval( $this->expire_user_settings['expire_user_date_in_num'] ) == 1 ) {

                    switch( $this->expire_user_settings['expire_user_date_in_block'] )  {

                        case 'days':   $block = esc_html__( '%d day',   'expire-users' ); break;
                        case 'weeks':  $block = esc_html__( '%d week',  'expire-users' ); break;
                        case 'months': $block = esc_html__( '%d month', 'expire-users' ); break;
                        case 'years':  $block = esc_html__( '%d year',  'expire-users' ); break;
                        default:       $block = '';
                    }
                }
                
                if ( intval( $this->expire_user_settings['expire_user_date_in_num'] ) > 1 ) {

                    switch( $this->expire_user_settings['expire_user_date_in_block'] )  {

                        case 'days':   $block = esc_html__( '%d days',   'expire-users' ); break;
                        case 'weeks':  $block = esc_html__( '%d weeks',  'expire-users' ); break;
                        case 'months': $block = esc_html__( '%d months', 'expire-users' ); break;
                        case 'years':  $block = esc_html__( '%d years',  'expire-users' ); break;
                        default:       $block = '';
                    }
                }

                $short = sprintf( $block, intval( $this->expire_user_settings['expire_user_date_in_num'] ));
                $expansion = sprintf( esc_html__( 'The extension is by %s from today.', 'expire-users' ), $short );

                switch( $this->role_meta['_expire_users_reminder_days'] ) {
                    case '1': $reminder_days = sprintf( esc_html__( '%s day', 'expire-users' ), $this->role_meta['_expire_users_reminder_days'] ); break;
                    default:  $reminder_days = sprintf( esc_html__( '%s days', 'expire-users' ), $this->role_meta['_expire_users_reminder_days'] ); break;
                }

                $predefined_fields['expire_users_reminder'] = array(

                                'title'        => esc_html__( 'Send User Reminder email in advance?', 'expire-users' ),
                                'metakey'      => 'expire_users_reminder',
                                'type'         => 'radio',
                                'label'        => sprintf( esc_html__( 'Send me a Reminder email %s in advance?', 'expire-users' ), $reminder_days ),
                                'help'         => sprintf( esc_html__( 'You can get a Reminder email %s before your User Role Expiration date.', 'expire-users' ), $reminder_days ),
                                'required'     => 0,
                                'public'       => 1,
                                'editable'     => true,
                                'default'      => 'no',
                                'options'      => array( 'yes' => esc_html__( 'Yes', 'expire-users' ),
                                                         'no'  => esc_html__( 'No', 'expire-users' ),
                                                        ),
                                'account_only' => true,
                            );

                if ( $this->user_expire_status( um_user( 'ID' )) == 'active' ) {

                    $predefined_fields['expire_users'] = array(

                                'title'        => esc_html__( 'Confirm User Role Expiration date renewal', 'expire-users' ),
                                'metakey'      => 'expire_users',
                                'type'         => 'radio',
                                'label'        => sprintf( esc_html__( 'Confirm User Role Expiration date renewal by %s', 'expire-users' ), $short ),
                                'help'         => sprintf( esc_html__( 'You must confirm your update of the User Role Expiration date before sending the update. %s', 'expire-users' ), $expansion ),
                                'required'     => 0,
                                'public'       => 1,
                                'editable'     => true,
                                'default'      => 'no',
                                'options'      => array( 'auto' => esc_html__( 'Yes, I confirm a new date', 'expire-users' ),
                                                         'no'   => esc_html__( 'No, keep current date', 'expire-users' ) ),
                                'account_only' => true,
                    );
                }

                if ( $this->user_expire_status( um_user( 'ID' )) == 'expired' ) {

                    $predefined_fields['expire_users'] = array(

                                'title'        => esc_html__( 'Renew User Role Expiration schedule', 'expire-users' ),
                                'metakey'      => 'expire_users',
                                'type'         => 'radio',
                                'label'        => sprintf( esc_html__( 'Renew User Role Expiration schedule by %s', 'expire-users' ), $short ),
                                'help'         => sprintf( esc_html__( 'Renew my User Account with my previous User Role. %s', 'expire-users' ), $expansion ),
                                'required'     => 0,
                                'public'       => 1,
                                'editable'     => true,
                                'default'      => 'no',
                                'options'      => array( 'auto' => esc_html__( 'Yes', 'expire-users' ),
                                                         'no'   => esc_html__( 'No', 'expire-users' ) ),
                                'account_only' => true,
                    );
                }
            }
        }

        return $predefined_fields;
    }

    public function create_shortcode_attr( $attr ) {

        $attrs = array();
        foreach( $attr as $key => $value ) {
            $attrs[] = $key . '="' . $value . '"';
        }

        return implode( ' ', $attrs );
    }

    public function do_shortcode_date( $role_name ) {

        $attr = array(
                        'date_format'    => $this->date_format,
                        'expires_format' => sprintf( esc_html__( 'Your User Role %151s Expires %252s',  'expire-users' ), $role_name, '%s' ),
                        'expired_format' => sprintf( esc_html__( 'Your User Role %151s Expired %252s',  'expire-users' ), $role_name, '%s' ),
                        'never_expire'   => sprintf( esc_html__( 'Your User Role %s will Never Expire', 'expire-users' ), $role_name ),
                    );

        return do_shortcode( '[expire_users_current_user_expire_date ' . $this->create_shortcode_attr( $attr ) . '/]' );
    }

    public function do_shortcode_countdown( $role_name ) {

        $attr = array(
                        'expires_format' => sprintf( esc_html__( 'Your User Role %151s Expires in %252s',  'expire-users' ), $role_name, '%s' ),
                        'expired_format' => sprintf( esc_html__( 'Your User Role %151s Expired %252s ago', 'expire-users' ), $role_name, '%s' ),
                        'expired'        => sprintf( esc_html__( 'Your User Role %s is Expired',           'expire-users' ), $role_name ),
                        'never_expire'   => sprintf( esc_html__( 'Your User Role %s will Never Expire',    'expire-users' ), $role_name ),
                    );

        return do_shortcode( '[expire_users_current_user_expire_countdown ' . $this->create_shortcode_attr( $attr ) . '/]' );
    }

    public function account_content_hook_expire_users( $output, $shortcode_args ) {

        $this->user_expire_status( um_user( 'ID' ));

        $days_left = absint( ( um_user( '_expire_user_date' ) - current_time( 'timestamp' )) / DAY_IN_SECONDS );
        $text = $days_left > 14 ? $this->do_shortcode_date( wp_roles()->get_names()[um_user( '_expire_users_role' )] ) :
                                  $this->do_shortcode_countdown( wp_roles()->get_names()[um_user( '_expire_users_role' )] );

        $output .= '<div class="um-field">' . $text . '</div><div class="um-clear"></div>';

        $args = 'expire_users';

        if ( isset( $this->role_meta['_expire_users_reminder'] ) && $this->role_meta['_expire_users_reminder'] == 1 ) {
                if ( $this->user_expire_status( um_user( 'ID' )) == 'active')  {
                    $reminder_timestamp = get_user_meta( um_user( 'ID' ), '_expire_users_reminder', true );
                    $send_status = ! empty( $reminder_timestamp ) ? sprintf( esc_html__( 'Reminder email will be sent to you %s', 'expire-users' ), date_i18n( $this->date_format, $reminder_timestamp ) ) : 
                                                                             esc_html__( 'Reminder email sent', 'expire-users' );
                    $send_status = ( um_user( 'expire_users_reminder' ) == 'yes' ) ? $send_status :
                                                                                     esc_html__( 'No reminder email', 'expire-users' );

                    $output .= '<div class="um-field">' .  $send_status . '</div>';
                    $args = 'expire_users_reminder,expire_users';
                }
        }

        if ( ! empty( $this->priority_role_id )) {
            $role_name = sprintf( esc_html__( 'Your User Role name now is %s', 'expire-users' ), wp_roles()->get_names()[$this->priority_role_id] );
            $output .= '<div class="um-field">' . $role_name . '</div><div class="um-clear"></div>';
        }

        if ( $this->user_expire_status( um_user( 'ID' ) ) == 'expired' ) {

            $role_name = sprintf( esc_html__( 'Expired User Role name is %s', 'expire-users' ), wp_roles()->get_names()[um_user( '_expire_users_role' )] );
            $output .= '<div class="um-field">' . $role_name . '</div><div class="um-clear"></div>';
            $args = 'expire_users'; // renew version
        }

        $fields = UM()->builtin()->get_specific_fields( $args );
        UM()->account()->init_displayed_fields( $fields, 'expire_users' );

        foreach ( $fields as $key => $data ) {

            if ( ! empty( $shortcode_args['is_block'] ) ) {
                $data['is_block'] = true;
            }

            $output .= str_replace( '[]', '', UM()->fields()->edit_field( $key, $data ));
        }

        $output .= wp_nonce_field( 'renew-user-now', 'renew_user_nonce'  );
        return $output;
    }

    public function get_role_capabilities() {

        if ( isset( $this->role_meta['name'] ) && ! empty( $this->role_meta['name'] ) ) {

            if ( $this->role_exclude_setting == '' || $this->role_meta['name'] == $this->role_exclude_setting ) {

                return false;

            } else {
 
                $this->role_meta['wp_capabilities'] = get_role( $this->get_role_id_by_role_name( $this->role_meta['name'] ))->capabilities;
                if ( isset( $this->role_meta['wp_capabilities']) && array_key_exists( 'manage_options', $this->role_meta['wp_capabilities']) && $this->role_meta['wp_capabilities']['manage_options'] == 1 ) {
             
                    return false;
                }
            }

        } else {

            return false;
        }
        
        if ( ! isset( $this->role_meta['_expire_users_role_selected'] ) || $this->role_meta['_expire_users_role_selected'] != 1 ) {

            return false;
        }

        return true;
    }

    public function customtab_expire_users( $user_id, $changes ) {

        global $expire_users;

        if ( $this->user_expire_status( $user_id ) !== false ) {
            if ( isset( $_REQUEST['renew_user_nonce'] ) && wp_verify_nonce( $_REQUEST['renew_user_nonce'], 'renew-user-now' )) {

                $role_renewal = false;

                if ( isset( $changes['expire_users'] ) && $changes['expire_users'] == 'auto' ) {
                    if ( $this->user_expire_status( $user_id ) == 'expired' ) {

                        if ( $this->get_role_capabilities() !== false ) {

                            UM()->roles()->set_role( $user_id, um_user( '_expire_users_role' ) );
                            $expire_users->user_register( $user_id );

                            if ( um_user( 'expire_users_reminder' ) == 'yes' ) {
                                $this->create_reminder_timestamp( $user_id );

                            } else {
                                delete_user_meta( $user_id, '_expire_users_reminder' );
                            }

                            $role_renewal = true;
                        }

                    } 
                    if ( $this->user_expire_status( $user_id ) == 'inactive' ) {

                        $expire_users->user_register( $user_id );
                        $this->save_current_role_id( $user_id );
                        $this->create_reminder_timestamp( $user_id );
                        $role_renewal = true;
                    }

                    update_user_meta( $user_id, 'expire_users', 'no' );
                }

                if ( ! $role_renewal && isset( $changes['expire_users_reminder'])) {

                    if ( $changes['expire_users_reminder'] == 'yes' ) {
                        $this->create_reminder_timestamp( $user_id );
                    } else {
                        delete_user_meta( $user_id, '_expire_users_reminder' );
                    }
                }

                $this->reload_um_user_cache( $user_id );
                $this->save_current_role_id( $user_id );

                if ( $role_renewal && UM()->options()->get( $this->templates['renewal'] . '_on' ) == 1 ) {

                    $this->user_expire_status( $user_id );
                    $args = $this->activate_placeholders();
                    UM()->mail()->send( $this->get_admin_email(), $this->templates['renewal'], $args );
                }
            }
        }
    }

    public function add_a_hidden_field_to_register_form( $args ) {
        echo '<input type="hidden" name="expire_users" value="auto" />';
    }

    public function add_a_hidden_field_to_login_form( $args ) {
        echo '<input type="hidden" name="expire_users" value="auto" />';
    }

    public function login_update_expire_users( $user_id ) {

        global $expire_users;

        if ( $this->user_expire_status( $user_id ) == 'active' ) {

            $expire_user_date = um_user( '_expire_user_date' );

            if ( ! isset( $expire_user_date ) || empty( $expire_user_date )) {

                if ( isset( $this->role_meta['_expire_users_login'] ) && $this->role_meta['_expire_users_login'] == 1 ) {

                    $expire_users->user_register( $user_id );

                    $this->create_reminder_timestamp( $user_id );
                    $this->save_current_role_id( $user_id );
                    $this->reload_um_user_cache( $user_id );

                    if ( UM()->options()->get( $this->templates['welcome'] . '_on' ) == 1 ) {
                        $args = $this->activate_placeholders();
                        UM()->mail()->send( um_user( 'user_email' ), $this->templates['welcome'], $args );
                    }
                }

            } else {

                if ( isset( $this->role_meta['_expire_users_each_login'] ) && $this->role_meta['_expire_users_each_login'] == 1 ) {

                    $expire_users->user_register( $user_id );

                    $this->create_reminder_timestamp( $user_id );
                    $this->save_current_role_id( $user_id );
                    $this->reload_um_user_cache( $user_id ); 
                }
            }
        }

        if ( $this->user_expire_status( $user_id ) == 'inactive' ) {

            if ( isset( $this->role_meta['_expire_users_login'] ) && $this->role_meta['_expire_users_login'] == 1 ) {

                $expire_users->user_register( $user_id );

                $this->create_reminder_timestamp( $user_id );
                $this->save_current_role_id( $user_id );
                $this->reload_um_user_cache( $user_id );

                if ( UM()->options()->get( $this->templates['welcome'] . '_on' ) == 1 ) {
                    $args = $this->activate_placeholders();
                    UM()->mail()->send( um_user( 'user_email' ), $this->templates['welcome'], $args );
                }
            }
        }
    }

    public function registration_set_extra_data( $user_id, $args, $form_data ) {

        global $expire_users;

        if ( isset( $form_data['mode'] ) && $form_data['mode'] == 'register' ) {

            if ( $this->user_expire_status( $user_id ) == 'active' ) {
                if ( isset( $this->role_meta['_expire_users_registration'] ) && $this->role_meta['_expire_users_registration'] == 1 ) {

                    $expire_users->user_register( $user_id );

                    $this->create_reminder_timestamp( $user_id );
                    $this->save_current_role_id( $user_id );
                    $this->reload_um_user_cache( $user_id );
                }
            }
        }
    }

    public function create_reminder_timestamp( $user_id ) {

        if ( isset( $this->role_meta['_expire_users_reminder'] ) && $this->role_meta['_expire_users_reminder'] == 1 ) {

            $expire_user_date = get_user_meta( $user_id, '_expire_user_date', true );

            if ( ! empty( $expire_user_date ) && ! empty( $this->role_meta['_expire_users_reminder_days'] )) {

                $reminder_user_date = intval( $expire_user_date ) - intval( $this->role_meta['_expire_users_reminder_days'] ) * DAY_IN_SECONDS;
                update_user_meta( $user_id, '_expire_users_reminder', $reminder_user_date );
                update_user_meta( $user_id, 'expire_users_reminder', 'yes' );
            }
        }
    }

    public function save_current_role_id( $user_id ) {

        if ( UM()->roles()->get_priority_user_role( $user_id ) != um_user( '_expire_users_role' )) {
            update_user_meta( $user_id, '_expire_users_role', $this->current_role_id );
        }
    }

    public function get_admin_email() {

        $admin_email = um_admin_email();
        if ( empty( $admin_email )) {
            $admin_email = bloginfo( 'admin_email' );
        }

        if ( isset( $this->role_meta['_expire_users_admin_email'])) {
            if ( is_email( $this->role_meta['_expire_users_admin_email'] )) {
                $admin_email = $this->role_meta['_expire_users_admin_email'];
            }
        }
        return $admin_email;
    }

    public function expire_user_email( $expired_user ) {

        $this->user_expire_status( $expired_user->user_id );

        $args = $this->activate_placeholders( true );

        if ( UM()->options()->get( $this->templates['user'] . '_on' ) == 1 ) {

            UM()->mail()->send( um_user( 'user_email' ), $this->templates['user'], $args );
        }

        if ( UM()->options()->get( $this->templates['admin'] . '_on' ) == 1 ) {

            um_fetch_user( $expired_user->user_id );
            UM()->mail()->send( $this->get_admin_email(), $this->templates['admin'], $args );
        }

        delete_user_meta( $expired_user->user_id, '_expire_users_reminder' );
        UM()->user()->remove_cache( $expired_user->user_id );
    }

    public function email_template_tags_patterns() {

        $search = array();

        $search[] = '{expiration-date}';
        $search[] = '{expiration-url}';
        $search[] = '{expiration-link}';
        $search[] = '{expiration-reminder}';
        $search[] = '{expiration-reminder-days}';
        $search[] = '{expiration-role}';

        return $search;
    }

    public function email_template_tags_replaces( $status ) {

        $expire_users_role = um_user( '_expire_users_role' );
        $role_name = ! empty( $expire_users_role ) ? wp_roles()->get_names()[$expire_users_role] : '';

        $renewal_text = $status ? sprintf( esc_html__( 'Renew User Role %s', 'expire-users' ), $role_name ) :
                                           esc_html__( 'Update User Role', 'expire-users' );

        $url           = esc_url( um_get_core_page( 'account' ) . 'expire_users/' );
        $reminder_timestamp = um_user( '_expire_users_reminder' );
        $reminder_date = isset( $reminder_timestamp ) ? date_i18n( $this->date_time_format, $reminder_timestamp ) : '';

        $replace = array();

        $replace[] = date_i18n( $this->date_time_format, um_user( '_expire_user_date' ));
        $replace[] = $url;
        $replace[] = '<a href="' . $url . '">' . $renewal_text . '</a>';
        $replace[] = $reminder_date;
        $replace[] = $this->role_meta['_expire_users_reminder_days'];
        $replace[] = $role_name;

        return $replace;
    }

    public function activate_placeholders( $status = false ) {

        $args = array();

        $args['tags']         = $this->email_template_tags_patterns();
        $args['tags_replace'] = $this->email_template_tags_replaces( $status );

        return $args;
    }

    public function email_notification_expire_users( $um_emails ) {

        if ( $this->get_default_expire_settings()) {

            $custom_emails = array(	$this->templates['welcome'] => array(
                                        'key'            => $this->templates['welcome'],
                                        'title'          => esc_html__( 'Expire Users - User Welcome', 'expire-users' ),
                                        'description'    => esc_html__( 'If template is active email is sent to user when included in User Role Expiration during login or backend renewal.', 'expire-users' ),
                                        'recipient'      => 'user',
                                        'default_active' => true,
                                        'subject'        => esc_html__( '[{site_name}] User Role Expiration Welcome', 'expire-users' ),
                                        'body'           => '',
                                ),

                                $this->templates['reminder'] => array(
                                        'key'            => $this->templates['reminder'],
                                        'title'          => esc_html__( 'Expire Users - User Reminder', 'expire-users' ),
                                        'description'    => esc_html__( 'If template is active an email is sent to the user as a Reminder of the upcoming User Role Expiration.', 'expire-users' ),
                                        'recipient'      => 'user',
                                        'default_active' => true,
                                        'subject'        => esc_html__( '[{site_name}] User Role Expiration Reminder', 'expire-users' ),
                                        'body'           => '',
                                ),

                                $this->templates['user'] => array(
                                        'key'            => $this->templates['user'],
                                        'title'          => esc_html__( 'Expire Users - User Role Expired', 'expire-users' ),
                                        'description'    => esc_html__( 'If template is active an email is sent to the User when the User Role is expired.', 'expire-users' ),
                                        'recipient'      => 'user',
                                        'default_active' => true,
                                        'subject'        => esc_html__( '[{site_name}] User Role Expired', 'expire-users' ),
                                        'body'           => '',
                                ),

                                $this->templates['admin'] => array(
                                        'key'            => $this->templates['admin'],
                                        'title'          => esc_html__( 'Expire Users - Admin about User Role Expired', 'expire-users' ),
                                        'description'    => esc_html__( 'If template is active an email is sent to the Site Admin about an User Role being expired.', 'expire-users' ),
                                        'recipient'      => 'admin',
                                        'default_active' => true,
                                        'subject'        => esc_html__( '[{site_name}] User Role Expired', 'expire-users' ),
                                        'body'           => '',
                                ),

                                $this->templates['renewal'] => array(
                                        'key'            => $this->templates['renewal'],
                                        'title'          => esc_html__( 'Expire Users - Admin about User Role Renewal', 'expire-users' ),
                                        'description'    => esc_html__( 'If template is active an email is sent to the Site Admin about a renewal by an Account with Expired User Role or via WP All Users backend renewal.', 'expire-users' ),
                                        'recipient'      => 'admin',
                                        'default_active' => true,
                                        'subject'        => esc_html__( '[{site_name}] User Role Renewal', 'expire-users' ),
                                        'body'           => '',
                                ),
                            );

            foreach ( $custom_emails as $slug => $custom_email ) {

                if ( UM()->options()->get( $slug . '_on' ) == '' ) {

                    $email_on = empty( $custom_email['default_active'] ) ? 0 : 1;
                    UM()->options()->update( $slug . '_on', $email_on );
                }

                if ( UM()->options()->get( $slug . '_sub' ) == '' ) {

                    UM()->options()->update( $slug . '_sub', $custom_email['subject'] );
                }

                $this->slugs[] = $slug;
            }

            $this->copy_email_notifications_expire_users();
            $um_emails = array_merge( $um_emails, $custom_emails );
        }
        return $um_emails;
    }

    public function copy_email_notifications_expire_users() {

        foreach ( $this->slugs as $slug ) {

            $located = UM()->mail()->locate_template( $slug );

            if ( ! is_file( $located ) || filesize( $located ) == 0 ) {
                $located = wp_normalize_path( get_stylesheet_directory() . '/ultimate-member/email/' . $slug . '.php' );
            }

            clearstatcache();
            if ( ! file_exists( $located ) || @filesize( $located ) == 0 ) {

                wp_mkdir_p( dirname( $located ) );

                $email_source = file_get_contents( Plugin_Path_EUR . 'templates/' . $slug . '.php' );
                file_put_contents( $located, $email_source );

                if ( ! file_exists( $located ) ) {
                    file_put_contents( um_path . 'templates/email/' . $slug . '.php', $email_source );
                }
            }
        }
    }

    public function get_role_id_by_role_name( $role_name ) {

        $wp_roles = wp_roles()->get_names();
        $roles_array = array_flip( $wp_roles );

        if ( isset( $roles_array[$role_name] ) ) {
            return $roles_array[$role_name];
        }        
        return false;
    }

    public function admin_role_metaboxes_expire_users( $roles_metaboxes ) {

        if ( $this->get_default_expire_settings()) {
                
            $roles_metaboxes[] = array(
                                        'id'       => 'um-admin-form-account-role-expiration',
                                        'title'    => esc_html__( 'User Role Expiration', 'expire-users' ),
                                        'callback' => array( $this, 'expire_users_metabox_callback' ),
                                        'screen'   => 'um_role_meta',
                                        'context'  => 'normal',
                                        'priority' => 'default',
            );
        }
        return $roles_metaboxes;
    }

    public function expire_users_metabox_callback( $object = array() ) {

        $role_data = $object['data'];

        if ( ! isset( $role_data['_um_is_custom'] ) || $role_data['_um_is_custom'] == 0 ) {
            if ( ! isset( $role_data['name'] ) || empty( $role_data['name'] )) {
                echo
                    '<div class="um-admin-metabox">' .
                    esc_html__( 'Role Name not found.', 'expire-users' ) ,
                    '</div>';
                return;
            }
            $role_data['wp_capabilities'] = get_role( $this->get_role_id_by_role_name( $role_data['name'] ) )->capabilities;
        }

        if ( isset( $role_data['wp_capabilities']) && array_key_exists( 'manage_options', $role_data['wp_capabilities']) && $role_data['wp_capabilities']['manage_options'] == 1 ) {
            echo
                '<div class="um-admin-metabox">' .
                esc_html__( 'Administrator User Roles are excluded from the UM User Role Expiration plugin settings.', 'expire-users' ) ,
                '</div>';
            return;
        }
        
        if ( $this->role_exclude_setting == '' || $role_data['name'] == $this->role_exclude_setting ) {
            echo
                '<div class="um-admin-metabox">' .
                esc_html__( 'Expire Users default Role is excluded from the UM User Role Expiration plugin settings.', 'expire-users' ) ,
                '</div>';
            return;
        }

        $day  = esc_html__( '%s day', 'expire-users' );
        $days = esc_html__( '%s days', 'expire-users' );

        $reminder_days = array( '1' => sprintf( $day, '1' ),
                                '2' => sprintf( $days, '2' ),
                                '3' => sprintf( $days, '3' ),
                                '4' => sprintf( $days, '4' ),
                                '5' => sprintf( $days, '5' ),
                                '6' => sprintf( $days, '6' ),
                                '7' => sprintf( $days, '7' ),
                                '8' => sprintf( $days, '8' ),
                                '9' => sprintf( $days, '9' ),
                                '10' => sprintf( $days, '10' ),
                                '11' => sprintf( $days, '11' ),
                                '12' => sprintf( $days, '12' ),
                                '13' => sprintf( $days, '13' ),
                                '14' => sprintf( $days, '14' ),
                            );

        ?>
        <div class="um-admin-metabox">
            <?php
            UM()->admin_forms(
                            array(
                                'class'     => 'um-role-my-metabox um-half-column',
                                'prefix_id' => 'role',
                                'fields'    => array(
                                                    array(
                                                        'id'      => '_expire_users_role_selected',
                                                        'type'    => 'checkbox',
                                                        'label'   => esc_html__( 'Include this Role in User Role Expiration?', 'expire-users' ),
                                                        'tooltip' => esc_html__( 'Activate the "Expire Users" plugin\'s UM integration for this User Role.', 'expire-users' ),
                                                        'value'   => isset( $role_data['_expire_users_role_selected'] ) ? $role_data['_expire_users_role_selected'] : '0',
                                                        ),
                                                    array(
                                                        'id'          => '_expire_users_registration',
                                                        'type'        => 'checkbox',
                                                        'label'       => esc_html__( 'Include Users during Registration?', 'expire-users' ),
                                                        'tooltip'     => esc_html__( 'New Users registered are included. Avoid this option, use the Login option instead, if you have email address confirmation or Admin approval for new Users. No email is sent, include info in your welcome email.', 'expire-users' ),
                                                        'value'       => isset( $role_data['_expire_users_registration'] ) ? $role_data['_expire_users_registration'] : 0,
                                                        'conditional' => array( '_expire_users_role_selected', '=', '1' ),
                                                        ),
                                                    array(
                                                        'id'          => '_expire_users_login',
                                                        'type'        => 'checkbox',
                                                        'label'       => esc_html__( 'Include an existing User at their next/first login?', 'expire-users' ),
                                                        'tooltip'     => esc_html__( 'Existing Users with this Role and without a free period are included at their next/first login and an email login notification is sent if enabled.', 'expire-users' ),
                                                        'value'       => isset( $role_data['_expire_users_login'] ) ? $role_data['_expire_users_login'] : 0,
                                                        'conditional' => array( '_expire_users_role_selected', '=', '1' ),
                                                        ),
                                                    array(
                                                        'id'          => '_expire_users_each_login',
                                                        'type'        => 'checkbox',
                                                        'label'       => esc_html__( 'Update the Expiration date at each User login?', 'expire-users' ),
                                                        'tooltip'     => esc_html__( 'A new Free User Role Expiration period is started at each User login. No User email login notification is sent for this option.', 'expire-users' ),
                                                        'value'       => isset( $role_data['_expire_users_each_login'] ) ? $role_data['_expire_users_each_login'] : 0,
                                                        'conditional' => array( '_expire_users_role_selected', '=', '1' ),
                                                        ),
                                                    array(
                                                        'id'          => '_expire_users_reminder',
                                                        'type'        => 'checkbox',
                                                        'label'       => esc_html__( 'Send an User Reminder email before Expiration day?', 'expire-users' ),
                                                        'tooltip'     => esc_html__( 'If selected Users may also enable/disable this option at their Account page tab for User Role Expiration.', 'expire-users' ),
                                                        'value'       => isset( $role_data['_expire_users_reminder'] ) ? $role_data['_expire_users_reminder'] : 0,
                                                        'conditional' => array( '_expire_users_role_selected', '=', '1' ),
                                                        ),
                                                    array(
                                                        'id'          => '_expire_users_reminder_days',
                                                        'type'        => 'select',
                                                        'label'       => esc_html__( 'User Reminder email number of days in advance?', 'expire-users' ),
                                                        'tooltip'     => esc_html__( 'Select the number of days between 1 and 14.', 'expire-users' ),
                                                        'options'     => $reminder_days,
                                                        'size'        => 'small',
                                                        'value'       => isset( $role_data['_expire_users_reminder_days'] ) ? $role_data['_expire_users_reminder_days'] : 0,
                                                        'conditional' => array( '_expire_users_reminder', '=', '1' ),
                                                     ),
                                                    array(
                                                        'id'          => '_expire_users_admin_email',
                                                        'type'        => 'text',
                                                        'label'       => esc_html__( 'Optional Admin email address?', 'expire-users' ),
                                                        'tooltip'     => esc_html__( 'If empty the default UM admin email address will be used.', 'expire-users' ),
                                                        'size'        => 'medium',
                                                        'value'       => isset( $role_data['_expire_users_admin_email'] ) ? $role_data['_expire_users_admin_email'] : '',
                                                        'conditional' => array( '_expire_users_role_selected', '=', '1' ),
                                                     ),
                                                )
                                )
                            )->render_form();
            ?>
            <div class="um-admin-clear"></div>
        </div>
        <?php
    }

    public function cron_schedule_update_recurrence() {

        // improvement user expiring within 24 hours do hourly otherwise do daily until any within 24 hours

        $this->get_default_expire_settings();
        if ( isset( $this->expire_user_settings['expire_user_date_in_block']) ) {
            $date = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ));
            switch( $this->expire_user_settings['expire_user_date_in_block'] ) {

                case 'months':
                case 'years':   $recurrence = array( 'interval' => $date->getTimestamp() + 12 * HOUR_IN_SECONDS,
                                                     'display'  => esc_html__( 'Twice daily', 'expire-users' ));
                                break;
                case 'days':
                case 'weeks':
                default:        $recurrence = array( 'interval' => $date->getTimestamp() + HOUR_IN_SECONDS,
                                                     'display'  => esc_html__( 'Hourly', 'expire-users' ));
                                break;
            }
        }
        return $recurrence;
    }

    public function um_expire_users_validation( $expired_users, $remind_email = false ) {

        global $expire_users;

        if ( count( $expired_users ) > 0 ) {
            foreach ( $expired_users as $key => $expired_user ) {               // validation when deleted from All Users??
                
                $slug = UM()->roles()->get_priority_user_role( $expired_user->ID );

                if ( ! empty( $slug )) {
                    if ( strpos( $slug, 'um_' ) === 0 ) {
                        $slug = substr( $slug, 3 );
                    }

                    if ( isset( $this->role_meta_cache_slug[$slug] )) {
                        $role_meta = $this->role_meta_cache_slug[$slug];
                    } else {
                        $role_meta = get_option( "um_role_{$slug}_meta", false );
                        $this->role_meta_cache_slug[$slug] = $role_meta;
                    }

                    if ( isset( $role_meta['_expire_users_role_selected'] ) && $role_meta['_expire_users_role_selected'] == 1 ) {

                        if ( $remind_email && ! isset( $this->role_meta_cache_uid[$expired_user->ID] )) {
                            $this->role_meta_cache_uid[$expired_user->ID] = $role_meta;
                        }

                        continue;
                    }
                }

                unset( $expired_users[$key] );
                foreach( $this->expire_users_metakeys as $metakey ) {
                    delete_user_meta( $expired_user->ID, $metakey );
                }
            }
        }
        return $expired_users;
    }

    public function um_expired_users_send_reminders() {

        global $wpdb;

        $timestamp = current_time( 'timestamp' );

        $sql = $wpdb->prepare( "SELECT u.ID
                                FROM {$wpdb->prefix}users u
                                INNER JOIN {$wpdb->prefix}usermeta m1 ON u.ID = m1.user_id AND m1.meta_key = '_expire_users_reminder'
                                INNER JOIN {$wpdb->prefix}usermeta m2 ON u.ID = m2.user_id AND m2.meta_key = 'expire_users_reminder'
                                WHERE m1.meta_value < %d
                                AND m2.meta_value = 'yes'",
                                $timestamp );

        $reminders = $this->um_expire_users_validation( $wpdb->get_results( $sql ), true );

        if ( count( $reminders ) > 0 ) {

            if ( UM()->options()->get( $this->templates['reminder'] . '_on' ) == 1 ) {

                foreach ( $reminders as $reminder ) {

                    $this->get_user_role_meta( $reminder->ID );
                    um_fetch_user( $reminder->ID );

                    if ( um_user( 'expire_users_reminder' ) == 'yes' ) {

                        $this->role_meta = $this->role_meta_cache_uid[$reminder->ID];
                        $args = $this->activate_placeholders();
                        UM()->mail()->send( um_user( 'user_email' ), $this->templates['reminder'], $args );
                    }

                    delete_user_meta( $reminder->ID, '_expire_users_reminder' );
                    UM()->user()->remove_cache( $reminder->ID );
                }

            } else {

                foreach ( $reminders as $reminder ) {
                    foreach( $this->expire_users_metakeys as $metakey ) {
                        delete_user_meta( $reminder->ID, $metakey );
                    }
                }
            }
        }
    }

    public function load_metabox_expire_users() {

        if ( $this->get_default_expire_settings()) {
            add_meta_box(   'um-metaboxes-sidebox-expire-users',
                            esc_html__( 'Expire User Roles', 'expire-users' ),
                            array( $this, 'toplevel_page_expire_users' ),
                            'toplevel_page_ultimatemember', 'side', 'core'
                        );
        }
    }

    public function utc_to_local_time( $timestamp, $format = '' ) {

        $format = empty( $format ) ? $this->date_time_format : $format;
        return get_date_from_gmt( date( 'Y-m-d H:i:s', $timestamp ), $format );
    }

    public function reload_um_user_cache( $user_id = '' ) {

        if ( isset( $user_id ) && ! empty( $user_id )) {
            UM()->user()->remove_cache( $user_id );
            um_fetch_user( $user_id );
        }
    }

    public function count_users_pending() {

        global $wpdb;

        $timestamp = current_time( 'timestamp' );

        $sql = $wpdb->prepare( "SELECT COUNT(*)
                                FROM {$wpdb->prefix}users u
                                INNER JOIN {$wpdb->prefix}usermeta m1 ON u.ID = m1.user_id AND m1.meta_key = '_expire_user_date'
                                INNER JOIN {$wpdb->prefix}usermeta m2 ON u.ID = m2.user_id AND m2.meta_key = '_expire_user_expired'
                                WHERE m1.meta_value < %d
                                AND m2.meta_value = 'N'",
                                $timestamp );

        $total = $wpdb->get_var( $sql );
        $total = (int)$total;

        return $total;
    }

    public function count_users_status( $status ) {

        $user_query = new WP_User_Query( array(
                                            'meta_key'     => '_expire_user_expired',
                                            'meta_value'   => $status,
                                            'meta_compare' => '=',
                                            'number'       => 1,
                                            'fields'       => 'ID',
                                            )
                                        );

        $total = $user_query->get_total();
        $total = $total > 0 ? $total : esc_html__( 'No', 'expire-users' );

        return $total;
    }

    public function count_users_expiring( $days ) {

        $today = current_time( 'timestamp' );
        switch( $days ) {
            case 1:  $period = $today + DAY_IN_SECONDS; break;
            case 7:  $period = $today + WEEK_IN_SECONDS; break;
            default: $period = $today + DAY_IN_SECONDS; break;
        }

        $args = array(
                        'meta_query' => array(
                            array(
                                'key'     => '_expire_user_date',
                                'value'   => array($today, $period),
                                'type'    => 'NUMERIC',
                                'compare' => 'BETWEEN',
                            ),
                        ),
                        'count_total' => true,
                        'number'      => 1,
                    );

        $user_query = new WP_User_Query( $args );

        $total = $user_query->get_total();
        $total = $total > 0 ? $total : esc_html__( 'No', 'expire-users' );

        return $total;
    }

    public function count_reminder_emails() {

        $today = current_time( 'timestamp' );
        $period = $today + DAY_IN_SECONDS;

        $args = array(
                'meta_query' => array(
                    array(
                        'key'     => '_expire_users_reminder',
                        'value'   => array($today, $period),
                        'type'    => 'NUMERIC',
                        'compare' => 'BETWEEN',
                    ),
                ),
                'count_total' => true,
                'number'      => 1,
            );

        $user_query = new WP_User_Query( $args );

        $total = $user_query->get_total();
        $total = $total > 0 ? $total : esc_html__( 'No', 'expire-users' );

        return $total;
    }

    public function toplevel_page_expire_users() {

        $cron_job = wp_next_scheduled( $this->cron_hook );
        $date = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ));
        
        if ( ! empty( $cron_job )) {
            if ( $cron_job != 1 ) {
                $next_cron_job = ( $cron_job > $date->getTimestamp()) ? sprintf( esc_html__( 'Next WP Cronjob scheduled at %s', 'expire-users' ), $this->utc_to_local_time( $cron_job, $this->time_date_format ) ) :
                                                                        sprintf( esc_html__( 'The WP Cronjob is in a queue to be serviced since %s', 'expire-users' ), $this->utc_to_local_time( $cron_job, $this->time_format ) );
            } else {
                $next_cron_job = esc_html__( 'The WP Cronjob is in a queue waiting for immediate service', 'expire-users' );
            }
        } else {
            $next_cron_job = sprintf( esc_html__( 'Retry! No active WP Cronjob found for this hook "%s". Reactivate the "Expire Users for UM" plugin.', 'expire-users' ), $this->cron_hook );
        }

        $pending = $this->count_users_pending();
        ?>
        <p>
        <?php
            echo $next_cron_job;
        ?>
        </p>
        <table>
            <tr><th style="text-align: left">
            <?php
                echo esc_html__( 'All User Expiration Roles', 'expire-users' );
            ?>  
            </th></tr>
            <tr><td style="padding-left: 8px">
            <?php
                switch( $pending ) {
                    case 0:     echo sprintf( esc_html__( '%s Users are not expired', 'expire-users' ), $this->count_users_status( 'N' ) ); break;
                    default:    echo sprintf( esc_html__( '%15s Users are not expired incl %25d Users are pending to be expired', 'expire-users' ), $this->count_users_status( 'N' ), $pending );
                }   
            ?>
            </td></tr>
            <tr><td style="padding-left: 8px">
            <?php
                echo sprintf( esc_html__( '%s Users are expired', 'expire-users' ), $this->count_users_status( 'Y' ) );
            ?>
            </td></tr>
            <tr><td style="padding-left: 8px">
            <?php
                switch( $pending ) {
                    case 0:     echo esc_html__( 'No Users are pending', 'expire-users' ); break;
                    default:    echo sprintf( esc_html__( '%d Users are pending to be expired by the next WP Cronjob or WP All Users list', 'expire-users' ), $pending );
                }                
            ?>
            </td></tr>
            <tr><td style="padding-left: 8px">
            <?php
                echo sprintf( esc_html__( '%s Users may expire during next 24 hours', 'expire-users' ), $this->count_users_expiring( 1 ) );
            ?>
            </td></tr>
            <tr><td style="padding-left: 8px">
            <?php
                echo sprintf( esc_html__( '%s Users may expire during next 7 days', 'expire-users' ), $this->count_users_expiring( 7 ) );
            ?>
            </td></tr>
            <tr><td style="padding-left: 8px">
            <?php
                echo sprintf( esc_html__( '%s Reminder emails will be sent during the nect 24 hours', 'expire-users' ), $this->count_reminder_emails( 1 ) );
            ?>
            </td></tr>
        </table>
        <?php
    }

    public function render_custom_filter_options( $which ) {

        if ( $this->get_default_expire_settings()) {
            if ( 'top' === $which && current_user_can( 'list_users' )) {

                $selected = array( '', '', '' );
                if ( isset( $_GET['um_expire_users'] ) && ! empty( $_GET['um_expire_users'] )) {
                    $selected[intval($_GET['um_expire_users'])] = ' selected="selected"';
                }
                ?>
                <div class="alignleft um-filter-by-status">
                <label class="screen-reader-text" for="um_filter_expire_users"><?php esc_html_e( 'Expire Users', 'expire-users' ); ?></label>
                <select name="um_expire_users" id="um_expire_users">
                    <option value=""><?php esc_html_e( 'UM Expire Users', 'expire-users' )?></option>
                    <option value="1"<?php echo $selected[1]?>><?php esc_html_e( 'User Roles not expired', 'expire-users' )?></option>
                    <option value="2"<?php echo $selected[2]?>><?php esc_html_e( 'User Roles expired', 'expire-users' )?></option>
                </select>
                <?php submit_button( __( 'Filter', 'expire-users' ), '', 'um_filter_expire_users', false ); ?>
                </div>
                <?php
            }
        }
    }

    public function filter_expire_users( $query ) {

        global $wpdb, $pagenow;

        if ( is_admin() && 'users.php' === $pagenow && isset( $_REQUEST['um_expire_users'] )) {

            switch( intval( sanitize_key( $_REQUEST['um_expire_users'] )) ) {
                case 1:  $status = 'N'; break;
                case 2:  $status = 'Y'; break;
                default: $status = false;
            }

            if ( ! empty( $status )) {
                $meta_query = array(
                                    array(
                                        'key'     => '_expire_user_expired',
                                        'value'   => $status,
                                        'compare' => '=',
                                    )
                                );

                $query->set( 'meta_query', $meta_query );
            }
        }

        return $query;
    }

    public function user_row_actions( $actions, $user_object ) {

        global $expire_users;

        if ( $this->get_default_expire_settings()) {
            if ( $expire_users->admin->current_expire_user_can( 'expire_users_edit' ) && $user_object->ID != get_current_user_id() ) {

                if ( $this->user_expire_status( $user_object->ID ) !== false ) {

                    $url = add_query_arg( 'um-renew-user', $user_object->ID );
                    $url = wp_nonce_url( $url, 'renew-user-now', 'renew_user_nonce' );

                    $renew = sprintf( '<a class="submitexpire" href="%s">%s</a>', esc_url( $url ), esc_html__( 'Renew Now', 'expire-users' ) );
                    if ( array_key_exists( 'expire', $actions )) {
                        $expire = $actions['expire'];
                        unset( $actions['expire'] );
                        $actions['expire'] = $expire;
                    }
                    $actions['renew'] = $renew;

                }
                if ( $this->user_expire_status( $user_object->ID ) === false ) {
                    if ( array_key_exists( 'expire', $actions )) {
                        unset( $actions['expire'] );
                    }
                }
                if ( $this->user_expire_status( $user_object->ID ) == 'inactive' ) {
                    if ( array_key_exists( 'expire', $actions )) {
                        unset( $actions['expire'] );
                    }
                }
            }
        }
		return $actions;
	}

    public function renew_user_now() {

		if ( isset( $_GET['um-renew-user'] ) && ! empty( $_GET['um-renew-user'] )) {
            if ( isset( $_GET['renew_user_nonce'] ) && wp_verify_nonce( $_GET['renew_user_nonce'], 'renew-user-now' ) ) {

                $user_id = absint( $_GET['um-renew-user'] );
                if ( $this->user_expire_status( $user_id ) != 'active' ) {

                    $_POST['expire_users'] = 'auto';
                    $this->customtab_expire_users( $user_id, array( 'expire_users_reminder' => 'yes', 'expire_users' => 'auto' ) );
                    unset( $_POST['expire_users'] );

                    if ( UM()->options()->get( $this->templates['welcome'] . '_on' ) == 1 ) {
                        um_fetch_user( $user_id );
                        $args = $this->activate_placeholders();
                        UM()->mail()->send( um_user( 'user_email' ), $this->templates['welcome'], $args );
                    }
                }
			}
		}
	}
}

global $um_expire_users;
$um_expire_users = new UM_Expire_Users();
