<?php

/**
 * Plugin Name: SpamJam
 * Plugin URI: https://utopique.net/products/spamjam/
 * Description: SpamJam silently kills spam in comments and registration.
 * Version: 0.5.1
 * Author: Utopique
 * Author URI: https://utopique.net/
 * Copyright: 2022-2024 Utopique
 * Text Domain: spamjam
 * License: GPLv2 or later
 * Requires at least: 5.2
 * Tested up to: 6.6
 * Requires PHP: 7.0
 * WC requires at least: 5.3
 * WC tested up to: 9.0.0
 * PHP version 7
 *
 * @category        SpamJam
 * @package         SpamJam
 * @author          Utopique <support@utopique.net>
 * @license         GPL https://utopique.net
 * @link            https://utopique.net
 */
namespace SpamJam;

\defined( 'ABSPATH' ) || exit;
/**
 * Constants.
 */
define( 'SPAMJAM_MAIN_FILE', __FILE__ );
/**
 * Spamjam FS
 */
if ( function_exists( __NAMESPACE__ . '\\spamjam_fs' ) ) {
    spamjam_fs()->set_basename( false, __FILE__ );
} else {
    if ( !function_exists( __NAMESPACE__ . '\\spamjam_fs' ) ) {
        /**
         * Create a helper function for easy SDK access.
         *
         * @return array
         */
        function spamjam_fs() {
            global $spamjam_fs;
            if ( !isset( $spamjam_fs ) ) {
                // Include Freemius SDK.
                require_once __DIR__ . '/vendor/freemius/wordpress-sdk/start.php';
                $spamjam_fs = fs_dynamic_init( [
                    'id'             => '11800',
                    'slug'           => 'spamjam',
                    'premium_slug'   => 'spamjam-pro',
                    'type'           => 'plugin',
                    'public_key'     => 'pk_a92dd1a28c55ffae9a6b997e98167',
                    'is_premium'     => false,
                    'premium_suffix' => 'Pro',
                    'has_addons'     => false,
                    'has_paid_plans' => true,
                    'menu'           => [
                        'slug'    => 'spamjam',
                        'support' => false,
                    ],
                    'is_live'        => true,
                ] );
            }
            return $spamjam_fs;
        }

        // Init Freemius.
        spamjam_fs();
        // Signal that SDK was initiated.
        do_action( 'spamjam_fs_loaded' );
    }
    /**
     * Load plugin translations.
     *
     * @return void
     */
    function load_textdomain() {
        load_plugin_textdomain( 'spamjam', false, basename( __DIR__ ) . '/languages/' );
    }

    add_action( 'plugins_loaded', __NAMESPACE__ . '\\load_textdomain' );
    /**
     * Block direct requests to wp-comments-post.php by checking the referrer URL.
     *
     * @return bool Returns true if the request should be blocked, false otherwise.
     */
    function dodgy_referer() {
        $referrer_url = wp_get_referer();
        // Block if no referer is provided.
        if ( false === $referrer_url || empty( $referrer_url ) ) {
            return true;
            // Referer is dodgy.
        }
        // Parse the referer URL to check if it's absolute by looking for a host component.
        $parsed_referer = wp_parse_url( $referrer_url );
        // If the referer doesn't have a host component, it's a relative URL and belongs to the site.
        if ( !isset( $parsed_referer['host'] ) ) {
            return false;
            // Referer belongs to the site (not dodgy).
        }
        // For absolute URLs, check if the referer's host matches the site's host.
        $site_host = wp_parse_url( home_url(), PHP_URL_HOST );
        if ( isset( $parsed_referer['host'] ) && $parsed_referer['host'] === $site_host ) {
            return false;
            // Referer is from the same site (not dodgy).
        }
        // If the referer is absolute and does not match the site's host, it's dodgy.
        return true;
    }

    /**
     * Comment blocklist.
     *
     * @param string $comment the comment.
     *
     * @return bool
     */
    function blocklist(  $comment  ) {
        $blocklist = [
            '[url=',
            'replica',
            'pill',
            'viagra',
            'cialis',
            'drug',
            'medicine',
            'prescription'
        ];
        $blocklist = apply_filters( 'spamjam_blocklist', $blocklist );
        foreach ( $blocklist as $string ) {
            if ( false !== stripos( $comment, $string ) ) {
                return true;
                // Comment contains a blocked string.
            }
        }
        return false;
        // No blocked strings found.
    }

    /**
     * Add Comment honeypot fields.
     *
     * @param html $comment_field html for $comment_field.
     *
     * @return html
     */
    function honeypot(  $comment_field  ) {
        $comment_field = str_replace( '="comment"', '="sj-comment"', $comment_field );
        ?>
        <p style="display: none;" aria-hidden="true" class="required">
        <label for="email_confirm">
            <?php 
        esc_html_e( 'confirm email:', 'spamjam' );
        ?>
        </label>
        <input type="email" name="email_confirm" id="email_confirm" size="30" />
        <?php 
        wp_nonce_field( 'spamjam_comment_nonce', 'spamjam_comment_nonce' );
        ?>
        <label for="comment">
            <?php 
        esc_html_e( 'fill comment:', 'spamjam' );
        ?>
        </label>
        <textarea id="comment" name="comment"></textarea>
        </p>
        <?php 
        return $comment_field;
    }

    /**
     * Note: the hook 'comment_form_after_fields' only targets
     * logged-out users.
     * Since we want to apply the security fields to all users, we'll use
     * 'comment_form_field_comment' (otherwise logged-in users get spamjammed)
     * so that the new fields are displayed before the submit button.
     */
    add_action( 'comment_form_field_comment', __NAMESPACE__ . '\\honeypot', 100 );
    /**
     * Add query string to comment form action.
     *
     * @return mixed
     */
    function comment_action_on_scroll() {
        // Check if Comment is enabled.
        if ( is_singular() && comments_open() ) {
            /**
             * Create a seed.
             */
            $comments_post = home_url( 'wp-comments-post.php' );
            $hash = hash( 'sha256', $comments_post );
            $action_url = add_query_arg( 'sj5', $hash, $comments_post );
            // Properly escape the warning message.
            $warning = esc_html__( 'Do not ', 'spamjam' );
            // Inline script for setting the form action dynamically.
            ?>
            <style>
                p.required label { clear: both; display: block; width: 100%; }
                p.required label::before { content: "⚠️ <?php 
            echo esc_js( $warning );
            ?>"; }
            </style>
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                var commentForm = document.querySelector("#commentform, #ast-commentform, #ht-commentform");
                if(commentForm) {
                    document.onscroll = function () { commentForm.action = "<?php 
            echo esc_url( $action_url );
            ?>"; };
                }
            });
            </script>
            <?php 
        }
    }

    add_action( 'wp_footer', __NAMESPACE__ . '\\comment_action_on_scroll', 99 );
    /**
     * Run all SpamJam tests.
     *
     * @param [type] $data comment data.
     *
     * @return $data
     */
    function spamjam_blocker(  $data  ) {
        // Initialize message string for collecting error codes.
        $error_messages = [];
        // add hash to array.
        if ( !empty( $_POST['sj5'] ) ) {
            $data['sj5'] = wp_kses_data( wp_unslash( $_POST['sj5'] ) );
        }
        /*
        // Debug.
            echo '<pre>';
            print_r($data);
            foreach ($_POST as $key => $value) {
                echo "{$key} = {$value}\r\n";
            }
            die; .
        */
        // Honeypot technique: check if a hidden field has been filled out.
        if ( !empty( $_POST['email_confirm'] ) ) {
            $error_messages[] = __( 'Honeypot field should be empty.', 'spamjam' );
        }
        // Validate and sanitize 'sj-comment' field from $_POST.
        if ( !empty( $_POST['sj-comment'] ) ) {
            $data['spam_content'] = wp_kses_post( wp_unslash( $_POST['sj-comment'] ) );
            // Check if the 'sj-comment' (spam content) field is filled.
            if ( !empty( $data['spam_content'] ) ) {
                $error_messages[] = __( 'Spam content detected.', 'spamjam' );
            }
        }
        // Referer is not correct.
        if ( dodgy_referer() ) {
            $error_messages[] = __( 'Referer verification failed.', 'spamjam' );
        }
        // Verify comment against a blocklist, assuming blocklist is a defined function.
        if ( !empty( $data['comment_content'] ) && blocklist( $data['comment_content'] ) ) {
            $error_messages[] = __( 'Your comment has been flagged as spam.', 'spamjam' );
        }
        // Nonce field checks for CSRF protection.
        if ( empty( $_POST['spamjam_comment_nonce'] ) || !wp_verify_nonce( wp_unslash( $_POST['spamjam_comment_nonce'] ), 'spamjam_comment_nonce' ) ) {
            $error_messages[] = __( 'Nonce verification failed.', 'spamjam' );
        }
        // MD5 hash check for additional form validation.
        $expected_sha256 = hash( 'sha256', home_url( 'wp-comments-post.php' ) );
        if ( empty( $_POST['sj5'] ) || wp_kses_data( wp_unslash( $_POST['sj5'] ) ) !== $expected_sha256 ) {
            $error_messages[] = __( 'Form validation failed.', 'spamjam' );
        }
        // Handling errors.
        if ( !empty( $error_messages ) ) {
            $error_message = implode( ' ', $error_messages );
            wp_die( wp_kses_post( "<p><strong>Error:</strong> {$error_message}</p>" ), esc_html__( 'Comment Submission Error', 'spamjam' ) );
        }
        // phpcs: ignore
        // store $_POST['sj-comment'] in $comment
        // $comment = wp_kses_data($_POST['sj-comment']);
        // store $_POST['comment'] in $data['sj-comment']
        // $data['sj-comment'] = wp_kses_data($_POST['comment']);
        // set $data['comment_content'] with $_POST['sj-comment']
        // $data['comment_content'] = $comment;
        // echo '<pre>'; print_r($data); die; .
        return $data;
    }

    // Process rules on the frontend only.
    if ( !is_admin() ) {
        add_filter( 'preprocess_comment', __NAMESPACE__ . '\\spamjam_blocker' );
    }
    /**
     * Swap our custom comment field to WP default.
     *
     * @return void
     */
    function comment_field_swap() {
        // Check if necessary POST variables are set.
        if ( isset( 
            $_POST['comment'],
            $_POST['sj-comment'],
            $_POST['spamjam_comment_nonce'],
            $_GET['sj5']
         ) ) {
            // Verify nonce here.
            if ( wp_verify_nonce( wp_unslash( $_POST['spamjam_comment_nonce'] ), 'spamjam_comment_nonce' ) ) {
                // Swap comments safely after nonce verification and unslashing.
                $comment = wp_kses_post( wp_unslash( $_POST['sj-comment'] ) );
                $_POST['sj-comment'] = wp_kses_post( wp_unslash( $_POST['comment'] ) );
                $_POST['comment'] = $comment;
                $_POST['sj5'] = wp_kses_post( wp_unslash( $_GET['sj5'] ) );
            }
        }
    }

    // add_action( 'init', __NAMESPACE__ . '\\comment_field_swap' );
    add_action( 'pre_comment_on_post', __NAMESPACE__ . '\\comment_field_swap' );
    /**
     * Declare WooCommerce HPOS support
     */
    add_action( 'before_woocommerce_init', function () {
        if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        }
    } );
    /**
     * Add admin settings page and menu.
     */
    require_once plugin_dir_path( __FILE__ ) . 'includes/admin.php';
}
// end spamjam_fs.