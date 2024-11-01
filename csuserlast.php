<?php

/* Plugin Name: UserLast
 * Description: The plugin shows the date and time of the user's last login.
 * Version: 1.1
 * Author: Code Sprinters sp. z o.o.
 * Author URI: https://codesprinters.pl/
 * License: GPLv2 or later
 */

class CS_User_Last_Login
{
    const META_KEY = 'cs_user_last_login_time';
    const LAST_LOGIN_TIME = 'Last Login';

    public function __construct()
    {
        add_action('wp_login', [$this, 'store_login_timestamp'], 10, 2);

        add_filter('manage_users_columns', [$this, 'add_last_login_column_to_user_list'], 10, 1);
        add_filter('manage_users_custom_column', [$this, 'show_last_login_column_on_users_list'], 10, 3);

        add_filter('manage_users_sortable_columns', [$this, 'make_last_login_column_on_user_list_sortable'], 10, 1);
        add_filter('users_list_table_query_args', [$this, 'sort_user_list_by_last_login_time'], 10, 1);

        add_action('edit_user_profile', [$this, 'show_last_login_on_user_edit'], 10, 1);
        add_action('show_user_profile', [$this, 'show_last_login_on_user_edit'], 10, 1);
    }

    public function store_login_timestamp($user_login, $user)
    {
        update_user_meta($user->ID, static::META_KEY, current_time('timestamp'));
    }

    public function add_last_login_column_to_user_list($columns)
    {
        $columns['cs_user_last_login_time'] = __(static::LAST_LOGIN_TIME);

        return $columns;
    }

    public function show_last_login_column_on_users_list($content, $column, $user_id)
    {
        if ('cs_user_last_login_time' !== $column) {
            return $content;
        }

        $lastLoginTime = get_user_meta($user_id, static::META_KEY, true);

        return !empty($lastLoginTime) ? date_i18n( 'Y-m-d H:i:s', $lastLoginTime ) : '-';
    }

    public function make_last_login_column_on_user_list_sortable($columns)
    {
        $columns['cs_user_last_login_time'] = ['cs_user_last_login_time', true];

        return $columns;
    }

    public function sort_user_list_by_last_login_time($args)
    {
        if (isset($args['orderby']) && $args['orderby'] === 'cs_user_last_login_time') {
            // The trick is needed to prevent removing users who never logged in.
            $args['meta_query'] = [
                'relation' => 'or',
                [
                    'key'     => static::META_KEY,
                    'compare' => 'exists',
                ],
                [
                    'key'     => static::META_KEY,
                    'compare' => 'not exists',
                ],
            ];

            $args['orderby'] = 'meta_value';
        }

        return $args;
    }

    public function show_last_login_on_user_edit($user)
    {
        $lastLoginTime = get_user_meta($user->ID, static::META_KEY, true); ?>
        
        <table class="form-table" role="presentation">
            <tr id="cs_user_last_login_time">
                <th><?php _e(static::LAST_LOGIN_TIME); ?></th>
                <td>
                    <?php if (!empty($lastLoginTime)): ?>
                        <?php echo date_i18n( 'Y-m-d H:i:s', $lastLoginTime ); ?>
                    <?php else: ?>
                        <?php echo '-' ?>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    <?php
    }
}

new CS_User_Last_Login();

function cs_user_last_login_activation()
{
    $users = get_users(['fields' => 'ID']);

    foreach ($users as $user) {
        $sessions = get_user_meta($user, 'session_tokens', true);

        $mostRecentLoginTimestamp = 0;

        foreach ($sessions as $session) {
            $sessionLoginTimestamp = isset($session['login']) ? (int) $session['login'] : 0;

            if ($sessionLoginTimestamp > $mostRecentLoginTimestamp) {
                $mostRecentLoginTimestamp = $sessionLoginTimestamp;
            }
        }

        if ($mostRecentLoginTimestamp === 0) {
            continue;
        }

        update_user_meta($user, 'cs_user_last_login_time', $mostRecentLoginTimestamp);
    }
}

function csuserlast_deactivate() {

    $users = get_users(['fields' => 'ID', 'meta_key' => 'cs_user_last_login_time', 'meta_compare' => 'exists']);
    
    foreach ( $users as $user_id ) {
        delete_user_meta( $user_id, 'cs_user_last_login_time' );
    }
}

register_activation_hook(__FILE__, 'cs_user_last_login_activation');
register_deactivation_hook( __FILE__, 'csuserlast_deactivate');