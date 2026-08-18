<?php
/**
 * Checkout billing fields without duplicate section headings.
 *
 * @package RezaJordaan
 * @version 3.6.0
 */

defined( 'ABSPATH' ) || exit;

$fields = $checkout->get_checkout_fields( 'billing' );
?>
<div class="woocommerce-billing-fields rj-checkout-billing-fields">
	<?php do_action( 'woocommerce_before_checkout_billing_form', $checkout ); ?>

	<div class="woocommerce-billing-fields__field-wrapper">
		<?php
		foreach ( $fields as $key => $field ) {
			woocommerce_form_field( $key, $field, $checkout->get_value( $key ) );
		}
		?>
	</div>

	<?php do_action( 'woocommerce_after_checkout_billing_form', $checkout ); ?>
</div>
