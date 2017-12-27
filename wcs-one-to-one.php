<?php
/*
* Plugin Name: WooCommerce Subscriptions One-to-One
* Plugin URI: https://github.com/Prospress/woocommerce-subscriptions-one-to-one/
* Description: Hides subscription products from the store that are currently subscribed to.
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
	PP_Dependencies::enqueue_admin_notice( '{plugin_name}', 'WooCommerce', '3.0' );
	return;
}

if ( false === PP_Dependencies::is_subscriptions_active( '2.1' ) ) {
	PP_Dependencies::enqueue_admin_notice( '{plugin_name}', 'WooCommerce Subscriptions', '2.1' );
	return;
}

register_activation_hook( __FILE__,'create_one_to_one_cache'); // creates array of product IDs in the options table when plugin is activated
add_filter( 'woocommerce_subscription_status_updated', 'update_one_to_one_cache', 10, 2 ); // updates the array whenever subscription status is updated
add_filter( 'woocommerce_product_is_visible', 'one_to_one_checker', 10, 2 ); // when displaying product on front end, hides product if is in the array
register_deactivation_hook( __FILE__, 'cleanup_one_to_one_cache' ); // deletes array when plugin is deactivated
}

/**
* creates array of product IDs in the options table when plugin is activated
*/
public function create_one_to_one_cache() {

	$products_with_subscription = [];

	$unended_subscriptions = wcs_get_subscriptions( array(
		'subscription_status' => array( 'active', 'pending', 'on-hold', 'pending-cancel' ),
	) );

	foreach ($unended_subscriptions as $subscription) {
		foreach ( $subscription->get_items() as $item_id => $line_item ) {
			$product_id = $line_item->get_product_id();
			if (!in_array($product_id, $products_with_subscription)) {
				$products_with_subscription[] = $product_id;
			}
		}
	}

	update_option( 'wcs_one_to_one_cache', $products_with_subscription );
}

/**
* updates the array whenever subscription status is updated
*
* @param instance of a WC_Subscription object
* @param string
* @param string
*/
public function update_one_to_one_cache($subscription, $new_status, $old_status) {

	$cache = get_option('wcs_unended_subs_cache');

	$unended_statuses = array('active', 'on-hold', 'pending-cancel');

	if (in_array($new_status, $unended_statuses) && !in_array($old_status, $unended_statuses)) {
		foreach ( $subscription->get_items() as $item_id => $line_item ) {
			$product_id = $line_item->get_product_id();
			// add product id to cache
			if (!in_array($product_id, $cache)) {
				$cache[] = $product_id;
			}
		}
		update_option( 'wcs_one_to_one_cache', $cache );

	} elseif (in_array($old_status, $unended_statuses) && !in_array($new_status, $unended_statuses)) {
		foreach ( $subscription->get_items() as $item_id => $line_item ) {
			$product_id = $line_item->get_product_id();
			// remove product id from cache
			$cache	= array_diff($cache, array($product_id));
		}
		update_option( 'wcs_one_to_one_cache', $cache );
	}

}

/**
* when displaying product on front end, hides product if is in the array
*
* @param boolean
* @param integer
*/
public function one_to_one_checker( $is_visible, $id ) {

	$cache = get_option('wcs_unended_subs_cache');
	if ($cache != false) {
		$product = wc_get_product( $id );

		if ( in_array($product->get_id(), $cache) {
			$is_visible = false;
		}
	}
	return $is_visible;
}

/**
* deletes array when plugin is deactivated
*/
function cleanup_one_to_one_cache() {
	delete_option( 'wcs_one_to_one_cache' );
}
