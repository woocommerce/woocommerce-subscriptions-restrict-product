<?php
/*
* Plugin Name: WooCommerce Subscriptions Restrict Product
* Plugin URI: https://github.com/Prospress/woocommerce-subscriptions-restrict-product/
* Description: Restricts subscription products to a certain number of total active (unended) subscriptions on a site.
* Author: Prospress Inc.
* Author URI: https://prospress.com/
* License: GPLv3
* Version: 1.0.0
* Requires at least: 4.0
* Tested up to: 4.8
*
* GitHub Plugin URI: Prospress/woocommerce-subscriptions-one-to-one
* GitHub Branch: master
*
* Copyright 2017 Prospress, Inc.  (email : freedoms@prospress.com)
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
*
* @package		WooCommerce Subscriptions
* @author		Prospress Inc.
* @since		1.0
*/

require_once( 'includes/class-pp-dependencies.php' );

if ( false === PP_Dependencies::is_woocommerce_active( '3.0' ) ) {
	PP_Dependencies::enqueue_admin_notice( 'WooCommerce Subscriptions Restrict Product', 'WooCommerce', '3.0' );
	return;
}

if ( false === PP_Dependencies::is_subscriptions_active( '2.1' ) ) {
	PP_Dependencies::enqueue_admin_notice( 'WooCommerce Subscriptions Restrict Product', 'WooCommerce Subscriptions', '2.1' );
	return;
}

// creates array of product IDs in the options table when plugin is activated
register_activation_hook( __FILE__,'create_wcs_restriction_cache' );

// deletes array when plugin is deactivated
register_deactivation_hook( __FILE__, 'cleanup_wcs_restriction_cache' );

// Append "Product Restriction" section in the Subscriptions settings tab.
add_filter( 'woocommerce_subscription_settings', 'restrict_product_admin_settings' );

// Save admin settings.
add_action( 'woocommerce_update_options_subscriptions', 'save_restrict_product_admin_settings' );

//Add product restriction option on edit product page
add_action( 'woocommerce_product_options_advanced', 'wcs_restriction_admin_edit_product_fields' );

// Save the data value from the custom fields
add_action( 'woocommerce_process_product_meta', 'wcs_restriction_save_product_fields' );

// updates the array whenever subscription status is updated
add_filter( 'woocommerce_subscription_status_updated', 'update_wcs_restriction_cache', 10, 3 );

// updates the array whenever subscription is created
add_filter( 'woocommerce_checkout_subscription_created', 'add_to_wcs_restriction_cache' );

// Prevents purchase of restricted products, but still allows manual renewals and payment of failed renewal orders
add_filter( 'woocommerce_subscription_is_purchasable', 'wcs_restriction_is_purchasable_renewal', 12, 2 );
add_filter( 'woocommerce_subscription_variation_is_purchasable', 'wcs_restriction_is_purchasable_renewal', 12, 2 );

// bypasses restriction for switches only if switching to same variable product
add_filter( 'woocommerce_subscription_variation_is_purchasable', 'wcs_restriction_is_purchasable_switch', 12, 2 );

// when displaying product on front end, hides product if restricted
add_filter( 'woocommerce_product_is_visible', 'wcs_restricted_is_purchasable', 10, 2 );

// add max to quantity selector on product page
add_filter( 'woocommerce_quantity_input_max', 'wcs_restriction_quantity_input_max', 1, 2 );

// Validating the quantity on add to cart action with the quantity of the same product available in the cart.
add_filter( 'woocommerce_add_to_cart_validation', 'wcs_restriction_qty_add_to_cart_validation', 1, 3 );

// Validate product quantity on cart update.
add_filter( 'woocommerce_update_cart_validation', 'wcs_restriction_update_validate_quantity', 10, 4 );

/**
* creates array of product IDs in the options table when plugin is activated
*/
function create_wcs_restriction_cache() {

	$products_with_subscription = [];

	$unended_subscriptions = wcs_get_subscriptions( array(
		'subscription_status' => array( 'active', 'pending', 'on-hold', 'pending-cancel' ),
	) );

	foreach ( $unended_subscriptions as $subscription ) {
		foreach ( $subscription->get_items() as $item_id => $line_item ) {
			$product_id = $line_item->get_product_id();
			$quantity = $line_item->get_quantity();
			if ( !array_key_exists( $product_id, $products_with_subscription ) ) {
				$products_with_subscription[$product_id] = 0;
			}
			$products_with_subscription[$product_id] += $quantity;
		}
	}

	update_option( 'wcs_restriction_cache', $products_with_subscription );
}

/**
* deletes array when plugin is deactivated
*/
function cleanup_wcs_restriction_cache() {
	delete_option( 'wcs_restriction_cache' );
}

/**
* updates the array whenever subscription is created
*
* @param instance of a WC_Subscription object
*/
function add_to_wcs_restriction_cache( $subscription, $order = null, $recurring_cart = null ) {
	$cache = get_option( 'wcs_restriction_cache' );

	foreach ( $subscription->get_items() as $item_id => $line_item ) {
		$product_id = $line_item->get_product_id();
		$quantity = $line_item->get_quantity();
		if ( !array_key_exists( $product_id, $cache ) ) {
			$cache[$product_id] = 0;
		}
		$cache[$product_id] += $quantity;
	}

	update_option( 'wcs_restriction_cache', $cache );
}

/**
* updates the array whenever subscription status is updated
*
* @param instance of a WC_Subscription object
* @param string
* @param string
*/
function update_wcs_restriction_cache( $subscription, $new_status, $old_status ) {
	$cache = get_option( 'wcs_restriction_cache' );

	$unended_statuses = array( 'active', 'pending', 'on-hold', 'pending-cancel' );

	if ( in_array( $new_status, $unended_statuses ) && !in_array( $old_status, $unended_statuses ) ) {
			foreach ( $subscription->get_items() as $item_id => $line_item ) {
          $product_id = $line_item->get_product_id();
          $quantity = $line_item->get_quantity();
          if ( ! array_key_exists( $product_id, $cache ) ) {
              $cache[ $product_id ] = 0;
          }
          $cache[ $product_id ] += $quantity;
      }
      update_option( 'wcs_restriction_cache', $cache );

	} elseif ( in_array( $old_status, $unended_statuses ) && !in_array( $new_status, $unended_statuses ) ) {
		foreach ( $subscription->get_items() as $item_id => $line_item ) {
			$product_id = $line_item->get_product_id();
			$quantity = $line_item->get_quantity();
			if ( $cache[$product_id] <= 1 ) {
				unset( $cache[$product_id] ); // remove product from array completely
			} elseif ( $cache[$product_id] > 1 ) {
				$cache[$product_id] -= $quantity; // or just reduce the number
			}
		}
		update_option( 'wcs_restriction_cache', $cache );
	}
}

/**
 * Append "Product Restriction" section in the Subscriptions settings tab.
 *
 * @param  array $settings
 * @return array
 */
function restrict_product_admin_settings( $settings ) {

	// Insert before miscellaneous settings.
	$misc_section_start = wp_list_filter( $settings, array( 'id' => 'woocommerce_subscriptions_miscellaneous', 'type' => 'title' ) );

	$spliced_array = array_splice( $settings, key( $misc_section_start ), 0, array(
		array(
			'name' => __( 'Product Restriction', 'woocommerce-subscriptions' ),
			'type' => 'title',
			'desc' => 'Restrict all subscription products to a total number of subscriptions on your site. This is a default setting that can be overwritten by the product-level settings.',
			'id'   => 'wcs_restrict_product_options',
		),

		array(
			'name' => __( 'Sitewide Product Restriction', 'woocommerce-subscriptions' ),
			'id'          => '_default_product_restriction',
			'label'       => __( 'Restrict all products', 'woocommerce-subscriptions' ),
			'desc_tip' => 'Leave blank or set to "0" to deactivate.',
			'placeholder' => '0',
			'type' => 'number',
			'custom_attributes' => array(
				'step' => '1',
				'min' => '0', )
			),
		array(
			'type' => 'sectionend',
			'id'   => 'wcs_restrict_product_options',
		),
	) );

	return $settings;
}

/**
 * Save product restriction settings from the WooCommerce > Settings > Subscriptions administration screen.
 *
 * @return void
 */
function save_restrict_product_admin_settings() {
	$default_product_restriction = $_POST['_default_product_restriction'];
	update_option( '_default_product_restriction', esc_attr( $default_product_restriction ) );
}

/**
* Adds restrict product option to 'Edit Product' screen.
*/
function wcs_restriction_admin_edit_product_fields() {
	global $post;

	echo '<div class="options_group restrict_product show_if_subscription show_if_variable-subscription"><p><strong>Restrict Product</strong></p>';
	woocommerce_wp_checkbox(
		array(
			'id'            => '_product_restrict_YN',
			'label'         => __('Activate product-level restriction?', 'woocommerce-subscriptions' ),
			'desc_tip' => 'true',
			'description'   => __( 'This will override any sitewide product restriction.', 'woocommerce-subscriptions' ),
			'value'         => get_post_meta( $post->ID, '_product_restrict_YN', true ),
			)
		);
	woocommerce_wp_text_input( array(
		'id'          => '_product_restriction',
		'label'       => __( 'Product-level restriction', 'woocommerce-subscriptions' ),
		'desc_tip' => 'true',
		'description' => __( 'Restrict product to a total number of unended subscriptions on your site. Activate on product level and leave blank to have product ignore sitewide restriction.'  ),
		'placeholder' => '',
		'type' => 'number',
		'custom_attributes' => array(
			'step' => '1',
			'min' => '0', )
		) );
		echo '</div>';

		do_action( 'woocommerce_subscriptions_product_options_advanced' );
}

/**
* Save the data value from the custom field
*
* @param int
*/
function wcs_restriction_save_product_fields( $post_id ) {
	$product_restrict_YN = $_POST['_product_restrict_YN'];
	update_post_meta( $post_id, '_product_restrict_YN', esc_attr( $product_restrict_YN ) );
	$product_restriction = $_POST['_product_restriction'];
	update_post_meta( $post_id, '_product_restriction', esc_attr( $product_restriction ) );
}

/**
* helper function to check product-level restriction, then fall back to default
*
* @param integer
* @return integer
*/
function wcs_restrict_product_get_restriction( $product_id ) {

	$product_restrict_YN = get_post_meta( $product_id, '_product_restrict_YN', true );
	$product_restriction = get_post_meta( $product_id, '_product_restriction', true );
	if ( 'yes' != $product_restrict_YN ) {
		$product_restriction = get_option( '_default_product_restriction', 0 );
	} elseif ( !isset( $product_restriction ) || empty( $product_restriction ) || 0 == $product_restriction ) {
		$product_restriction = 0;
	}

	return $product_restriction;
}

/**
* main helper function that makes it not purchasable by checking against cache to see if subscription is currently restricted
*
* @param boolean
* @param integer
* @return boolean
*/
function wcs_restricted_is_purchasable( $is_purchasable, $id ) {

	if ( $is_purchasable ) {
		$cache = get_option( 'wcs_restriction_cache' );
		$product_restriction_option = wcs_restrict_product_get_restriction( $id );

		// uncomment the next line to print the current cache
		// error_log( 'cache:' . print_r($cache, TRUE ) );

		if ( $cache != false ) {
			if ( isset($cache[$id] ) && ( $product_restriction_option > 0 ) && ( $cache[$id] >= $product_restriction_option ) ) {
				$is_purchasable = false;
			}
		}
	}

	return $is_purchasable;
}

/**
* Determines whether a product is purchasable based on whether the cart is set to resubscribe or renew.
*
* @param boolean
* @param instance of WC_Product object
* @return boolean
*/
function wcs_restriction_is_purchasable_renewal( $is_purchasable, $product ) {

	// check if restricted first
	$is_purchasable = wcs_restricted_is_purchasable( $is_purchasable, $product->get_id() );

	// then, allow to be purchased if renewal or resubscribe
	if ( false === $is_purchasable ) {
		// Resubscribe logic
		if ( isset( $_GET['resubscribe'] ) || false !== ( $resubscribe_cart_item = wcs_cart_contains_resubscribe() ) ) {
			$subscription_id       = ( isset( $_GET['resubscribe'] ) ) ? absint( $_GET['resubscribe'] ) : $resubscribe_cart_item['subscription_resubscribe']['subscription_id'];
			$subscription          = wcs_get_subscription( $subscription_id );
			if ( false != $subscription && $subscription->has_product( $product->get_id() ) && wcs_can_user_resubscribe_to( $subscription ) && 'pending-cancel' != $subscription->get_status() ) {
				$is_purchasable = true;
			}
			// Renewal logic
		} elseif ( isset( $_GET['subscription_renewal'] ) || wcs_cart_contains_renewal() ) {
			$is_purchasable = true;
			// Restoring cart from session, so need to check the cart in the session (wcs_cart_contains_renewal() only checks the cart)
		} elseif ( WC()->session->cart ) {
			foreach ( WC()->session->cart as $cart_item_key => $cart_item ) {
				if ( $product->get_id() == $cart_item['product_id'] && ( isset( $cart_item['subscription_renewal'] ) || isset( $cart_item['subscription_resubscribe'] ) ) ) {
					$is_purchasable = true;
					break;
				}
			}
		}
	}

	return $is_purchasable;
}

/**
* bypasses restriction for switches only if switching to same variable product
*
* @param boolean
* @param instance of WC_Product object
* @return boolean
*/
function wcs_restriction_is_purchasable_switch( $is_purchasable, $product ) {

	// Check if the product is restricted first
	$is_purchasable = wcs_restricted_is_purchasable( $is_purchasable, $product->get_parent_id() );

	// We're only concerned with variable products during switch because individual grouped products should be handled on their own.
	if ( false === $is_purchasable && 'subscription_variation' === $product->get_type() ) {
		// If the customer has requested to switch
		if ( isset( $_GET['switch-subscription'] ) ) {
			// check if the product is a variation of the same parent product
			$subscription = wcs_get_subscription( $_GET['switch-subscription'] );
			$line_item_id = $_GET['item'];
			foreach ( $subscription->get_items() as $item_id => $item_values ) {
				$product_id = $item_values->get_product_id();
				if ( ( $line_item_id == $item_id ) && ( $product_id == $product->get_parent_id() ) ) {
					$is_purchasable = true;
				}
			}
			// If the cart contains a switch to this product
		} elseif ( WC_Subscriptions_Switcher::cart_contains_switch_for_product( $product ) ) {
			$is_purchasable = true;
			// If restoring cart from session, the cart doesn't exist so cart_contains_switch_for_product will fail.
		} elseif ( isset( WC()->session->cart ) ) {
			foreach ( WC()->session->cart as $cart_item_key => $cart_item ) {
				if ( $product->get_parent_id() == $cart_item['product_id'] && isset( $cart_item['subscription_switch'] ) ) {
					$is_purchasable = true;
					break;
				}
			}
		}
	}

	return $is_purchasable;
}

/**
* helper function that gets quantity of given product in the cart
*
* @param int
* @return int
*/
function get_product_quantity_in_cart( $product_id ) {
	$quantity = 0;

	if ( ! empty( WC()->cart->cart_contents ) ) {
		foreach ( WC()->cart->cart_contents as $cart_item ) {
			if ( $cart_item['product_id'] == $product_id ) {
				$quantity += $cart_item['quantity'];
			}
		}
	}

	return $quantity;
}



/**
* add max to quantity selector on product page
*
* @param int
* @param instance of WC_Product object
* @return int
*/
function wcs_restriction_quantity_input_max( $max, $product ) {

	$product_restriction_option = $num_active_subs_for_product = $quantity_in_cart = 0;
	// (the total number allowed - the total quantity of active subscriptions to this product - number of products in the cart)
	$product_restriction_option = wcs_restrict_product_get_restriction( $product->get_id() );

	if ( 0 == $product_restriction_option ) {
		$max = 9999;
	} else {
		$cache = ( null !== get_option( 'wcs_restriction_cache' ) ? get_option( 'wcs_restriction_cache' ) : 0);
		$num_active_subs_for_product = ( isset( $cache[$product->get_id()] ) ? $cache[$product->get_id()] : 0);
		$quantity_in_cart = get_product_quantity_in_cart(	$product->get_id() );
		$max = $product_restriction_option - $num_active_subs_for_product - $quantity_in_cart;
	}
	return $max;
};

/**
* Validating the quantity on add to cart action with the quantity of the same product available in the cart.
*
* @param boolean
* @param int
* @param int
* @param int
* @param array
* @return boolean
*/
function wcs_restriction_qty_add_to_cart_validation( $passed, $product_id, $quantity) {
	$product = wc_get_product( $product_id );
	$product_max = wcs_restriction_quantity_input_max( 0, $product );
	if ( ! empty( $product_max ) ) {
		if ( false == $product_max ) {
			return $passed;
		}
	}

	if ( $quantity > $product_max ) {
		$passed = false;
		wc_add_notice( apply_filters( 'isa_wc_max_qty_error_message_already_had', sprintf( __( 'This product is restricted. You can add a maximum of %1$s more to %2$s.', 'woocommerce-max-quantity' ),
		$product_max,
		'<a href="' . esc_url( wc_get_cart_url() ) . '">' . __( 'your cart', 'woocommerce-max-quantity' ) . '</a>'),
		$product_max),
		'error' );
	}

	return $passed;
}


/**
* Validate product quantity on cart update.
*
* @param boolean
* @param string
* @param array
* @param int
* @return boolean
*/
function wcs_restriction_update_validate_quantity( $passed, $cart_item_key, $values, $quantity ) {

	$cart_item = WC()->cart->get_cart_item( $cart_item_key );
	$product_id = $cart_item['product_id'];
	$product = wc_get_product( $product_id );

	$product_restriction_option = $num_active_subs_for_product = $quantity_in_cart = 0;
	$product_restriction_option = wcs_restrict_product_get_restriction( $product_id );
	$cache = ( null !== get_option( 'wcs_restriction_cache' ) ? get_option( 'wcs_restriction_cache' ) : 0 );
	$num_active_subs_for_product = ( isset( $cache[$product_id] ) ? $cache[$product_id] : 0 );
	$quantity_in_cart = get_product_quantity_in_cart(	$product->get_parent_id() );
	$product_max = $product_restriction_option - $num_active_subs_for_product - $quantity_in_cart;

	if ( ! empty( $product_max ) ) {
		if ( false == $product_max ) {
			return $passed;
		}
	}

	if ( $quantity > $product_max ) {
		$passed = false;
		wc_add_notice( apply_filters( 'isa_wc_max_qty_error_message_already_had', sprintf( __( 'This product is restricted. You can have a maximum of %1$s in %2$s.', 'woocommerce-max-quantity' ),
		$product_max,
		'<a href="' . esc_url( wc_get_cart_url() ) . '">' . __( 'your cart', 'woocommerce-max-quantity' ) . '</a>'),
		$product_max),
		'error' );
	}

	return $passed;
}
