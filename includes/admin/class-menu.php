<?php

/**
 * Administration Menu Class
 *
 * @package payroll
 */
class WDP_Admin_Menu {

    /**
     * Kick-in the class
     *
     * @return void
     */
    public function __construct() {
        add_action( 'init', array( $this, 'do_mode_switch' ) );

        add_action( 'admin_menu', array( $this, 'admin_menu' ), 99 );
        add_action( 'admin_menu', array( $this, 'hide_admin_menus' ) );

        add_action( 'admin_bar_menu', array( $this, 'admin_bar_mode_switch' ), 9999 );
    }

    /**
     * Get the admin menu position
     *
     * @return int the position of the menu
     */
    public function get_menu_position() {
        return apply_filters( 'payroll_menu_position', null );
    }


    /**
     * Mode/Context switch for ERP
     *
     * @param WP_Admin_Bar $wp_admin_bar The admin bar object
     */
    public function admin_bar_mode_switch( $wp_admin_bar ) {
        // bail if current user doesnt have cap
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $modules      = erp_get_modules();
        $current_mode = erp_get_current_module();

        $title        = __( 'Switch ERP Mode', 'wp-erp' );
        $icon         = '<span class="ab-icon dashicons-randomize"></span>';
        $text         = sprintf( '%s: %s', __( 'ERP Mode', 'wp-erp' ), $current_mode['title'] );


        $wp_admin_bar->add_menu( array(
            'id'        => 'erp-mode-switch',
            'title'     => $icon . $text,
            'href'      => '#',
            'position'  => 0,
            'meta'      => array(
                'title' => $title
            )
        ) );

        foreach ($modules as $key => $module) {
            $wp_admin_bar->add_menu( array(
                'id'     => 'erp-mode-' . $key,
                'parent' => 'erp-mode-switch',
                'title'  => $module['title'],
                'href'   => wp_nonce_url( add_query_arg( 'erp-mode', $key ), 'erp_mode_nonce', 'erp_mode_nonce' )
            ) );
        }
    }

    /**
     * Do the admin mode switch
     *
     * @return void
     */
    public function do_mode_switch() {
        global $current_user;

        // bail if current user doesnt have cap
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // check for our nonce
        if ( ! isset( $_GET['erp_mode_nonce'] ) || ! wp_verify_nonce( $_GET['erp_mode_nonce'], 'erp_mode_nonce' ) ) {
            return;
        }

        $modules = erp_get_modules();

        // now check for our query string
        if ( ! isset( $_REQUEST['erp-mode'] ) || ! array_key_exists( $_REQUEST['erp-mode'], $modules ) ) {
            return;
        }

        update_user_meta( $current_user->ID, '_erp_mode', $_REQUEST['erp-mode'] );

        wp_redirect( admin_url( 'index.php' ) );
        exit;
    }

    /**
     * Add menu items
     *
     * @return void
     */
    public function admin_menu() {
        add_menu_page( __( 'ERP', 'wp-erp' ), __( 'ERP Settings', 'wp-erp' ), 'manage_options', 'erp-dashboard', array( $this, 'dashboard_page' ), 'dashicons-admin-tools', $this->get_menu_position() );

        add_submenu_page( 'erp-dashboard', __( 'Company', 'wp-erp' ), __( 'Company', 'wp-erp' ), 'manage_options', 'erp-company', array( $this, 'company_page' ) );
        add_submenu_page( 'erp-dashboard', __( 'Settings', 'wp-erp' ), __( 'Settings', 'wp-erp' ), 'manage_options', 'erp-settings', array( $this, 'employee_page' ) );
    }

    /**
     * Hide default WordPress menu's
     *
     * @return void
     */
    function hide_admin_menus() {
        // remove_menu_page( 'index.php' );                  //Dashboard
        remove_menu_page( 'edit.php' );                   //Posts
        remove_menu_page( 'upload.php' );                 //Media
        remove_menu_page( 'edit.php?post_type=page' );    //Pages
        remove_menu_page( 'edit-comments.php' );          //Comments
        remove_menu_page( 'themes.php' );                 //Appearance
        // remove_menu_page( 'plugins.php' );                //Plugins
        remove_menu_page( 'users.php' );                  //Users
        remove_menu_page( 'tools.php' );                  //Tools
        // remove_menu_page( 'options-general.php' );        //Settings
    }

    /**
     * Handles the dashboard page
     *
     * @return void
     */
    public function dashboard_page() {
        echo "Dashboard!";
    }

    /**
     * Handles the employee page
     *
     * @return void
     */
    public function employee_page() {
        echo "employee!";
    }

    /**
     * Handles the company page
     *
     * @return void
     */
    public function company_page() {
        include_once dirname( __FILE__ ) . '/views/company.php';
    }

    /**
     * Handles the company locations page
     *
     * @return void
     */
    public function locations_page() {
        include_once dirname( __FILE__ ) . '/views/locations.php';
    }
}

return new WDP_Admin_Menu();