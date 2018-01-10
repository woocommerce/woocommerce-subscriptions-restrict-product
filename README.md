# WooCommerce Subscriptions Restrict Product

Restricts subscription products to a certain number of total active (unended) subscriptions on a site.

Use case: store owner wants products and subscriptions to have a one-to-one relationship, i.e. each product should only be subscribed to once. For example, subscription product is a child to be sponsored, and only one subscriber should be sponsoring that child at a time.

This plugin creates and maintains a list of products that have unended subscriptions associated with them, then makes these products unpurchasable from front end to make sure that the subscription product can only be subscribed to a certain number of times. It doesn't change ability to check out with product, so that customer can pay for failed order through cart, and manual renewals are unaffected.

Note: This plugin is designed to work independently of the WooCommerce inventory system, and so inventory should be disabled for all subscription products, or you may have problems with customers not being able to pay for failed renewal orders manually.

## Installation

To install:

1. Download the latest version of the plugin [here](https://github.com/Prospress/woocommerce-subscriptions-restrict-product/archive/master.zip)
1. Go to **Plugins > Add New > Upload** administration screen on your WordPress site
1. Select the ZIP file you just downloaded
1. Click **Install Now**
1. Click **Activate**

### Updates

To keep the plugin up-to-date, use the [GitHub Updater](https://github.com/afragen/github-updater).

## Reporting Issues

If you find a problem or would like to request this plugin be extended, please [open a new Issue](https://github.com/Prospress/woocommerce-subscriptions-restrict-product/issues/new).

---

<p align="center">
	<a href="https://prospress.com/">
		<img src="https://cloud.githubusercontent.com/assets/235523/11986380/bb6a0958-a983-11e5-8e9b-b9781d37c64a.png" width="160">
	</a>
</p>
