<?php

/**
 * Admin
 * PHP version 7
 *
 * @category Admin
 * @package  SpamJam
 * @author   Utopique <support@utopique.net>
 * @license  GPL https://utopique.net
 * @link     https://utopique.net
 */
namespace SpamJam;

\defined( 'ABSPATH' ) || exit;
/**
 * Add menu item
 *
 * @return void
 */
function add_menu_item() {
    add_menu_page(
        'SpamJam Settings',
        // Page title.
        'Spamjam',
        // Menu title.
        'manage_options',
        // Capability required to access the page.
        'spamjam',
        // Menu slug.
        __NAMESPACE__ . '\\settings_page',
        // Function to display the settings page.
        // 'dashicons-admin-generic', // Icon URL.
        'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" width="34" height="34" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="2" d="M8 2a4 4 0 0 0-2 7.465V16h12V9.465A4 4 0 0 0 16 2H8Zm3.321 4.874a1.004 1.004 0 0 1 1.38-.37l1.715.991c.483.279.652.889.37 1.38l-.991 1.715a1.004 1.004 0 0 1-1.38.37L10.7 9.968a1.004 1.004 0 0 1-.37-1.379l.991-1.716ZM8 18v2m4-2v5m4-5v3"/></svg>' ),
        // Icon URL.
        32
    );
}

add_action( 'admin_menu', __NAMESPACE__ . '\\add_menu_item' );
/**
 * Register settings
 *
 * @return void
 */
function register_settings() {
    // Register settings if not already done.
    register_setting( 'spamjam-settings-group', 'spamjam_use_premium_blocklist', 'intval' );
    register_setting( 'spamjam-settings-group', 'spamjam_update_blocklist_automatically', 'intval' );
    register_setting( 'spamjam-settings-group', 'spamjam_protect_registration', 'intval' );
}

add_action( 'admin_init', __NAMESPACE__ . '\\register_settings' );
/**
 * HTML for our settings page
 *
 * @return void
 */
function settings_page() {
    $disabled = 'disabled';
    ?>
    <div class="wrap">
        <h1>SpamJam Settings</h1>
        <p>The settings below only apply to <a href="<?php 
    echo esc_url( \SpamJam\spamjam_fs()->get_upgrade_url() );
    ?>">SpamJam Pro</a>.</p>

        <form method="post" action="options.php">
            <?php 
    settings_fields( 'spamjam-settings-group' );
    ?>
            <?php 
    do_settings_sections( 'spamjam-settings-group' );
    ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Use Premium Blocklist</th>
                    <td>
                        <label class="switch">
                            <input type="checkbox" name="spamjam_use_premium_blocklist" value="1" <?php 
    checked( 1, get_option( 'spamjam_use_premium_blocklist', 0 ) );
    ?> aria-label="Use Premium Blocklist" <?php 
    echo $disabled;
    ?>>
                            <span class="slider round"></span>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Update Blocklist Automatically</th>
                    <td>
                        <label class="switch">
                            <input type="checkbox" name="spamjam_update_blocklist_automatically" value="1" <?php 
    checked( 1, get_option( 'spamjam_update_blocklist_automatically', 0 ) );
    ?> aria-label="Update Blocklist Automatically" <?php 
    echo $disabled;
    ?>>
                            <span class="slider round"></span>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Protect registration from spam</th>
                    <td>
                        <label class="switch">
                            <input type="checkbox" name="spamjam_protect_registration" value="1" <?php 
    checked( 1, get_option( 'spamjam_protect_registration', 0 ) );
    ?> aria-label="Protect registration from spam" <?php 
    echo $disabled;
    ?>>
                            <span class="slider round"></span>
                        </label>
                    </td>
                </tr>
            </table>
            <?php 
    submit_button();
    ?>
        </form>
    </div>
    <style>
        /* The switch - the box around the slider */
        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }

        /* Hide default HTML checkbox */
        .switch input {display:none;}

        /* The slider */
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            -webkit-transition: .4s;
            transition: .4s;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            -webkit-transition: .4s;
            transition: .4s;
        }

        input:checked + .slider {
            background-color: #2196F3;
        }

        input:focus + .slider {
            box-shadow: 0 0 1px #2196F3;
        }

        input:checked + .slider:before {
            -webkit-transform: translateX(26px);
            -ms-transform: translateX(26px);
            transform: translateX(26px);
        }

        /* Rounded sliders */
        .slider.round {
            border-radius: 34px;
        }

        .slider.round:before {
            border-radius: 50%;
        }

        .switch input:focus + .slider {
            box-shadow: 0 0 1px #2196F3; /* Ensure this is visually distinct */
        }
    </style>
    <?php 
}
