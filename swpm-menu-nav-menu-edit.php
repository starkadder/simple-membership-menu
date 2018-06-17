<?php
    /**
     * Swpm menu item groups management
     *
     * Copyright 2015 Giovanni CLEMENT(email: giovanni.clement@gmail.com)
     *
     **/
    class Swpm_menu_custom_groups extends Walker_Nav_Menu_Edit {
        /**
         * Start the element output.
         *
         * We're injecting our custom fields after the div.submitbox
         *
         * @see Walker_Nav_Menu::start_el()
         *
         * @param string $output Passed by reference. Used to append additional content.
         * @param object $item   Menu item data object.
         * @param int    $depth  Depth of menu item. Used for padding.
         * @param array  $args   Menu item args.
         * @param int    $id     Nav menu ID.
         */
        function start_el( &$output, $item, $depth = 0, $args = array(), $id = 0 ) {
            $item_output = '';
            parent::start_el( $item_output, $item, $depth, $args, $id );
            $output .= preg_replace(
            // NOTE: Check this regex from time to time!
                '/(?=<fieldset[^>]+class="[^"]*field-move)/',
                $this->get_fields( $item, $depth, $args ),
                $item_output
            );
        }
        /**
         * Get custom fields
         *
         * @access protected
         * @uses add_action() Calls 'menu_item_custom_fields' hook
         *
         * @param object $item  Menu item data object.
         * @param int    $depth  Depth of menu item. Used for padding.
         * @param array  $args  Menu item args.
         * @param int    $id    Nav menu ID.
         *
         * @return string Form fields
         */
        protected function get_fields( $item, $depth, $args = array(), $id = 0 ) {
            ob_start();
            do_action( 'wp_nav_menu_item_custom_fields', $id, $item, $depth, $args );
            return ob_get_clean();
        }
    }