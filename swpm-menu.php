<?php
    /*
    Plugin Name: Simple Membership Menu
    Plugin URI: ---
    Description: Hide menu items following configuration
    Version: 1.0.0
    Author: Giovanni CLEMENT
    Author URI: ---
    License: GPL2

    Copyright 2015 Giovanni CLEMENT(email: giovanni.clement@gmail.com)

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA02110-1301USA

    */

    if ( ! function_exists( 'is_admin' ) ) {
        header( 'Status: 403 Forbidden' );
        header( 'HTTP/1.1 403 Forbidden' );
        exit();
    }

    function debug($message) {
      echo($message . "<br />"); 
    }

    function isVisible($item, $list) {
      $n = 0;
      for($i = 0; $i < count($list); ++$i) {
	if  ($item == $list[$i]) {
	  $n += 1;
	}
      }
      return ($n == 0);
    }


    if ( ! class_exists( "Swpm_menu" ) ) :

        class Swpm_menu {


            /**
             * @var menu The single instance of the class
             */
            protected static $_instance = null;

            /**
             * @var string version number
             * @since 1.0.0
             */
            public $version = '1.0.0';

            const META_KEY_NAME = 'menu-item-swpm';
            const NOT_LOGGED_IN_LEVEL_ID = -1;

            /**
             * Main instance
             *
             * Ensures only one instance is loaded or can be loaded.
             *
             * @static
             * @see Nav_Menu_Roles()
             * @return Swpm_menu - Main instance
             */
            public static function instance() {
                if ( is_null( self::$_instance ) ) {
                    self::$_instance = new self();
                }
                return self::$_instance;
            }

            /**
             * Cloning is forbidden
             */
            public function __clone() {
                _doing_it_wrong( __FUNCTION__, __( 'FORBIDDEN' , 'swpm_menu'), '1.0' );
            }

            /**
             * Unserializing instances of this class is forbidden.
             *
             */
            public function __wakeup() {
                _doing_it_wrong( __FUNCTION__, __( 'FORBIDDEN' , 'swpm_menu'), '1.0' );
            }

            /**
             * Constructor.
             * @access public
             * @return instance
             * @since  1.0
             */
            function __construct(){

                //// Admin hooks
                if(is_admin())
                {
                    add_filter( 'wp_edit_nav_menu_walker', array( $this, 'add_swpm_menu_editor' ), 99 );
                    add_action( 'wp_update_nav_menu_item', array( $this, 'save_swpm_menu_groups' ), 10, 3 );
                    add_action( 'wp_nav_menu_item_custom_fields', array( $this, 'add_swpm_fields' ), 10, 4 );
                }
                else
                {
                    //// Regular hooks :  exclude items via filter instead of via custom Walker
                    add_filter('wp_get_nav_menu_items', array($this, 'exclude_menu_items'));
                }
            }

            /**
             * Replace default menu editor walker with ours
             *
             * We don't actually replace the default walker. We're still using it and
             * only injecting some HTMLs.
            **/
            function add_swpm_menu_editor( $walker ) {
                $walker = 'Swpm_menu_custom_groups';
                if ( ! class_exists( $walker ) ) {
                    require_once dirname( __FILE__ ) . '/swpm-menu-nav-menu-edit.php';
                }
                return $walker;
            }

            /**
             * Print field
             *
             * @param object $item  Menu item data object.
             * @param int    $depth  Depth of menu item. Used for padding.
             * @param array  $args  Menu item args.
             * @param int    $id    Nav menu ID.
             *
             * @return string Form fields
             */
            function add_swpm_fields( $id, $item, $depth, $args ) {

                //// Logged levels
                $levels = $this->get_membership_levels();

                $item_groups = get_post_meta( $item->ID, self::META_KEY_NAME, true );

                ?>
                    <p class="description description-wide menu-item-actions">
                        Hide if : <br/>
                        <?php foreach($levels as $level)
                        {
                            $key = self::META_KEY_NAME.'-'.$level->id;
                            $name  = sprintf( '%s[%s]', $key, $item->ID );
                            $checked = is_array($item_groups) && in_array($level->id, $item_groups) ? "checked" : "";
                            ?>
                            <label class="menu-item-title" style="padding-top:8px;padding-bottom:8px;">
                               <?php printf(
                                 '<input type="checkbox" class="menu-item-checkbox" name="%3$s" value="%1$s" %4$s> %2$s',
                                 $level->id,
                                 $level->alias,
                                 $name,
                                 $checked); ?><br/>
                            </label>
                        <?php } ?>
                    </p>
                <?php
            }

            /**
             * Save custom rights value
             *
             * @wp_hook action wp_update_nav_menu_item
             *
             * @param int   $menu_id         Nav menu ID
             * @param int   $menu_item_db_id Menu item ID
             * @param array $menu_item_args  Menu item data
             */
            function save_swpm_menu_groups( $menu_id, $menu_item_db_id, $menu_item_args ) {
                if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
                    return;
                }

                //// We control that only admin can use this
                check_admin_referer( 'update-nav_menu', 'update-nav-menu-nonce' );

                //// Membership level
                $levels = $this->get_membership_levels();

                //// Form state
                $groups = array();

                foreach ( $levels as $level ) {

                    $key = self::META_KEY_NAME.'-'.$level->id;

                    if ( ! empty( $_POST[ $key ][ $menu_item_db_id ] ) ) {
                        $groups[] = $_POST[ $key ][ $menu_item_db_id ];
                    }
                }

                // Update
                update_post_meta( $menu_item_db_id, self::META_KEY_NAME, $groups );
            }

            /**
             * Exclude menu items via wp_get_nav_menu_items filter
             * It use simple membership auth instance to manage session status
             */
            function exclude_menu_items( $items ) {

                $hide_children_of = array();

                // SWPM auth instance
                $auth = SwpmAuth::get_instance();
                $is_logged = $auth->is_logged_in();

                //// Getting if auth, user group
                $level = $is_logged ? $auth->get('membership_level') : self::NOT_LOGGED_IN_LEVEL_ID;

		
		// Iterate over the items to search and destroy
		foreach ( $items as $key => $item ) {
		  
		  $item_groups = get_post_meta( $item->ID, self::META_KEY_NAME, true );
		  
		  // hide any item that is the child of a hidden item
		  if( in_array( $item->menu_item_parent, $hide_children_of ) ){
		    $visible = false; // was false  tschweiger
		    $hide_children_of[] = $item->ID; // for nested menus
		  }
		  
		  //// Check rights
		  $visible =  isVisible($level, $item_groups);
		  
		  
		  // add filter to work with plugins that don't use traditional roles
		  $visible = apply_filters( 'swpm_menu_item_visibility', $visible, $item );
		  
		  // unset non-visible item
		  if ( ! $visible ) {
		    $hide_children_of[] = $item->ID; // store ID of item
		    unset( $items[$key] ) ;
		  }
		}
                return $items;
            }

            function get_membership_levels() {

                global $wpdb;

                //// Not logged level
                $not_logged_level = new stdClass();
                $not_logged_level->id = self::NOT_LOGGED_IN_LEVEL_ID;
                $not_logged_level->alias = "Not logged in";

                $query = "SELECT * FROM " . $wpdb->prefix . "swpm_membership_tbl WHERE id !=1";
                $membership_level_resultset = $wpdb->get_results($query);
                $membership_level_resultset = $membership_level_resultset ? $membership_level_resultset : array();

                array_unshift($membership_level_resultset, $not_logged_level);

                return $membership_level_resultset;
            }


        } // end class

    endif; // class_exists check


    /**
     * Launch the whole plugin
     * Returns the main instance of Swpm menu to prevent the need to use globals.
     *
     */
    function Swpm_menu() {
        return Swpm_menu::instance();
    }

// Global for backwards compatibility.
    $GLOBALS['SWPM_menu'] = Swpm_menu();