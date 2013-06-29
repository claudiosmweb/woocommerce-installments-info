<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly.

/**
 * WC_Installments_Info class.
 */
class WC_Installments_Info {

    /**
     * Construct.
     */
    public function __construct() {

        // Add view in WooCommerce products single.
        $views = get_option( 'wcii_design' );

        if ( $views['display'] == 0 ) {
            add_action( 'woocommerce_after_single_product_summary', array( &$this, 'print_view' ), 50 );

        } else if ( $views['display'] == 1 ) {
            add_action( 'woocommerce_after_single_product_summary', array( &$this, 'print_view' ), 1 );

        } else if ( $views['display'] == 2 ) {
            add_action( 'woocommerce_product_tabs', array( &$this, 'tab_view' ), 60 );
            add_action( 'woocommerce_product_tab_panels', array( &$this, 'display_tab_view' ), 60 );
        } else if ( $views['display'] == 3 ) {
            add_action( 'woocommerce_single_product_summary', array( &$this, 'print_view' ), 35 );
        }

        // Add Shortcode.
        add_shortcode( 'wcii', array( &$this, 'shortcode' ) );
        add_action( 'init', array( &$this, 'shortcode_buttons_init' ) );

        // Front-end scripts.
        add_action( 'wp_enqueue_scripts', array( &$this, 'front_scripts' ) );
    }

    /**
     * Front-End Scripts.
     */
    public function front_scripts() {
        if ( is_product() ) {
            wp_register_style( 'wcii', plugins_url( 'css/styles.css', __FILE__ ), array(), null, 'all' );
            wp_enqueue_style( 'wcii' );
        }
    }

    /**
     * Number format.
     *
     * @param  float  $price Number of Product price.
     * @return string        Number formatted.
     */
    public function number_format( $price ) {
        global $woocommerce;

        $num_decimals = (int) get_option( 'woocommerce_price_num_decimals' );

        $price = apply_filters( 'raw_woocommerce_price', (double) $price );

        $price = number_format( $price, $num_decimals, stripslashes( get_option( 'woocommerce_price_decimal_sep' ) ), stripslashes( get_option( 'woocommerce_price_thousand_sep' ) ) );

        if ( get_option( 'woocommerce_price_trim_zeros' ) == 'yes' && $num_decimals > 0 ) {
            $price = woocommerce_trim_zeros( $price );
        }

        return $price;
    }

    /**
     * Format price.
     *
     * @param  string $price Product price.
     * @return string        Price formatted.
     */
    public function format_price( $price ) {
        global $woocommerce;

        $return = '';
        $currency_pos = get_option( 'woocommerce_currency_pos' );
        $currency_symbol = get_woocommerce_currency_symbol();

        $price = $this->number_format( $price );

        switch ( $currency_pos ) {
            case 'left' :
                $return = $currency_symbol . $price;
            break;
            case 'right' :
                $return = $price . $currency_symbol;
            break;
            case 'left_space' :
                $return = $currency_symbol . '&nbsp;' . $price;
            break;
            case 'right_space' :
                $return = $price . '&nbsp;' . $currency_symbol;
            break;
        }

        return $return;
    }

    /**
     * Installment configurations.
     *
     * @param  float  $price            Product price.
     * @param  int    $parcel_maximum   Quantity of parcels.
     * @param  int    $parcel_minimum   Parcels minimum.
     * @param  mixed  $iota             iota.
     * @param  int    $without_interest Parcels without interest.
     * @param  float  $interest         Interest.
     * @param  int    $calculation_type Calculation type (0 - Amortization schedule or 1 - Simple interest);
     * @return string                   Table with parcels and interest.
     */
    public function view(
        $price,
        $parcel_maximum,
        $parcel_minimum,
        $iota,
        $without_interest,
        $interest,
        $calculation_type ) {

        // Get design options.
        $design = get_option( 'wcii_design' );

        // Get icons
        $icons = get_option( 'wcii_icons', '' );

        // Float ou margin.
        $align = ( $design['float'] == 'center' ) ? ' margin-left: auto; margin-right: auto' : ' float: ' . $design['float'];

        $table = '';

        if ( $price > $iota ) {

            // Open the table.
            $table .= '<div id="wc-credit-cart-table" class="clear" style="width: ' . $design['width'] . ';' . $align . ';">';

            if ( $design['display'] != 2 ) {
                $table .= '<h3>' . $design['title'] . '</h3>';
            }

            // Border wrapper.
            $table .= '<div id="wc-credit-cart-table-wrap" style="border-color:' . $design['border'] . ';">';

            $table .= '<div class="part left">';

            $count = 0;
            for ( $p = $parcel_minimum; $p <= $parcel_maximum; $p++ ) {

                $background = ( $count %  2 ) ? $design['even'] : $design['odd'];

                // Without interest.
                if ( $p <= $without_interest ) {
                    $parcel_value = $price / $p;
                }

                // With interest.
                if ( $p > $without_interest ) {

                    if ( $calculation_type == 0 ) {
                        $parcel_value = ( $price * ( $interest / 100 ) ) / ( 1 - ( 1 / ( pow( 1 + ( $interest / 100 ), $p ) ) ) );
                    }

                    if ( $calculation_type == 1 ) {
                        $parcel_value = ( $price * pow( 1 + ( $interest / 100 ), $p ) ) / $p;
                    }

                }

                // Test iota.
                if ( $parcel_value >= $iota ) {

                    if ( $p <= $without_interest ) {
                        $table .= '<span class="card-info" style="color: ' . $design['without'] . '; background: ' . $background . '; border-color: ' . $design['border'] . ';">' . sprintf( __( '%s%sx%s of %s %swithout interest%s', 'wcii' ), '<strong>', $p, '</strong>', $this->format_price( $parcel_value ), '<em>', '</em>' ) . '</span>';
                    } else {
                        $table .= '<span class="card-info" style="background: ' . $background . '; border-color: ' . $design['border'] . ';">' . sprintf( __( '%s%sx%s of %s %swith interest%s', 'wcii' ), '<strong>', $p, '</strong>' , $this->format_price( $parcel_value ), '<em>', '</em>' ) . '</span>';
                    }

                }

                if ( $p == intval( $parcel_maximum / 2 ) ) {
                    $table .= '</div>';

                    $table .= '<div class="part right">';
                }

                $count++;
            }

            $table .= '</div>';

            // Close the border wrapper.
            $table .= '<div class="clear"></div>';
            $table .= '</div>';

            // Details.
            $table .= '<div id="wc-credit-cart-table-details">';

            // Show interest info.
            if ( $without_interest < $parcel_maximum ) {
                $table .= '<span>' . sprintf( __( 'Interest of %s%s per month', 'wcii' ), $this->number_format( $interest ), '%' ) . '</span>';
            }

            // Show maximum parcel info.
            if ( $iota > 0 ) {
                $table .= '<span>' . sprintf( __( ' (parcel minimum of %s)', 'wcii' ), $this->format_price( $iota ) ) . '</span>';
            }

            // Close the details.
            $table .= '</div>';

            // Display credit card icons.
            if ( $icons != '' ) {

                $table .= '<div id="wc-credit-cart-table-icons">';
                $table .= '<strong>' . __( 'Pay with: ', 'wcii' ) . '</strong>';

                foreach ( $icons as $key => $value ) {
                    $table .= sprintf( '<span class="card-icons card-%s"></span>', $value );
                }

                $table .= '<div class="clear"></div>';
                $table .= '</div>';
            }


            // Close the table.
            $table .= '</div>';
        }

        return $table;
    }

    /**
     * Print table view.
     */
    public function print_view() {
        global $product;

        // Get settings.
        $default = get_option( 'wcii_settings' );

        echo $this->view( $product->get_price(), $default['parcel_maximum'], $default['parcel_minimum'], $default['iota'], $default['without_interest'], $default['interest'], $default['calculation_type'] );
    }

    /**
     * Create a new tab.
     */
    public function tab_view() {
        echo '<li class="wcii_tab"><a href="#tab-wcii">' . apply_filters( 'wcii_tab_title' , __( 'Credit Card Parcels', 'wcii' ) ) . '</a></li>';
    }

    /**
     * Display tab content.
     */
    public function display_tab_view() {
        global $product;

        // Get settings.
        $default = get_option( 'wcii_settings' );

        $html = '<div class="panel entry-content" id="tab-wcii">';
            $html .= $this->view( $product->price, $default['parcel_maximum'], $default['parcel_minimum'], $default['iota'], $default['without_interest'], $default['interest'], $default['calculation_type'] );
        $html .= '</div>';

        echo $html;
    }

    /**
     * Table shortcode.
     */
    public function shortcode( $atts ) {
        global $product;

        // Get default settings.
        $default = get_option( 'wcii_settings' );

        extract(
            shortcode_atts(
                array(
                    'price'            => $product->price,
                    'parcel_maximum'   => $default['parcel_maximum'],
                    'parcel_minimum'   => $default['parcel_minimum'],
                    'iota'             => $default['iota'],
                    'without_interest' => $default['without_interest'],
                    'interest'         => $default['interest'],
                    'calculation_type' => $default['calculation_type']
                ), $atts
            )
        );

        return $this->view( $price, $parcel_maximum, $parcel_minimum, $iota, $without_interest, $interest, $calculation_type );
    }

    /**
     * Add custom buttons in TinyMCE.
     */
    public function shortcode_register_buttons( $buttons ) {
        array_push( $buttons, '|', 'wcii' );
        return $buttons;
    }

    /**
     * Register button scripts.
     */
    public function shortcode_add_buttons( $plugin_array ) {
        $plugin_array['wcii'] = plugins_url( 'tinymce/wcii.js' , __FILE__ );
        return $plugin_array;
    }

    /**
     * Register buttons in init.
     */
    public function shortcode_buttons_init() {
        if ( !current_user_can( 'edit_posts' ) && !current_user_can( 'edit_pages' ) ) {
            return;
        }

        if ( get_user_option( 'rich_editing') == 'true' ) {
            add_filter( 'mce_external_plugins', array( &$this, 'shortcode_add_buttons' ) );
            add_filter( 'mce_buttons', array( &$this, 'shortcode_register_buttons' ) );
        }
    }

}