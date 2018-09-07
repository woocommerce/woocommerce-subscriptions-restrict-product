<?php

add_action( 'admin_menu', function() {
    add_dashboard_page(
        __( 'Cache display', 'woocommerce-subscriptions' ),
        __( 'Cache display', 'woocommerce-subscriptions' ),
        'manage_options',
        'cache-display',
        'render_cache_display_page'
    );
} );

function render_cache_display_page() {
  echo '<h1>WooCommerce Subscriptions Restrict Product</h1><h3>Unended Subscriptions per Product Cache</h3><table class="widefat fixed" cellspacing="0"><thead><tr><th  id="columnname" class="manage-column column-columnname" scope="col">Product Name</th><th  id="columnname" class="manage-column column-columnname" scope="col">  Product ID  </th><th  id="columnname" class="manage-column column-columnname" scope="col">  # of unended subscriptions</th></tr></thead><tbody>';
    $cache = get_option( 'wcs_restriction_cache' );
    $i = 0;
    foreach ( $cache as $key => $value ) {
      $product = wc_get_product( $key );
      $productname = $product->get_title();
      if ($i % 2 == 0) {
        echo '<tr class="alternate">';
      } else {
        echo '<tr>';
      }
      echo '<td>' . $productname . '</td><td>' . $key . '</td><td>' . $value . '</td></tr>';
      $i++;
    }
    echo '</tbody></table>';
}

// remove it from the menu
add_action( 'admin_head', function() {
    remove_submenu_page( 'index.php', 'cache-display' );
} );
