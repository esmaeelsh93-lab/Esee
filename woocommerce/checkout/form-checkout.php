<?php
/**
 * Branded classic checkout form.
 *
 * @package RezaJordaan
 * @version 9.4.0
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_checkout_form', $checkout );

if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
	echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'برای ثبت سفارش وارد حساب کاربری شوید.', 'rezajordaan' ) ) );
	return;
}
?>

<form name="checkout" method="post" class="checkout woocommerce-checkout rj-checkout-form" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data" aria-label="<?php esc_attr_e( 'تسویه‌حساب', 'rezajordaan' ); ?>">
	<div class="rj-checkout-layout">
		<section class="rj-checkout-panel rj-checkout-customer" aria-labelledby="rj-checkout-customer-title">
			<header class="rj-checkout-panel__header">
				<span><?php esc_html_e( 'اطلاعات تحویل', 'rezajordaan' ); ?></span>
				<h2 id="rj-checkout-customer-title"><?php esc_html_e( 'سفارش را کجا تحویل دهیم؟', 'rezajordaan' ); ?></h2>
				<p><?php esc_html_e( 'اطلاعات گیرنده را دقیق بنویسید تا هماهنگی ارسال سریع‌تر انجام شود.', 'rezajordaan' ); ?></p>
			</header>

			<?php if ( $checkout->get_checkout_fields() ) : ?>
				<?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>

				<div id="customer_details" class="rj-checkout-customer__fields">
					<?php do_action( 'woocommerce_checkout_billing' ); ?>
					<?php do_action( 'woocommerce_checkout_shipping' ); ?>

					<?php if ( $checkout->get_checkout_fields( 'order' ) ) : ?>
						<div class="woocommerce-additional-fields rj-checkout-order-notes">
							<?php foreach ( $checkout->get_checkout_fields( 'order' ) as $key => $field ) : ?>
								<?php woocommerce_form_field( $key, $field, $checkout->get_value( $key ) ); ?>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>

				<?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>
			<?php endif; ?>
		</section>

		<aside class="rj-checkout-panel rj-checkout-summary" aria-labelledby="order_review_heading">
			<?php do_action( 'woocommerce_checkout_before_order_review_heading' ); ?>

			<header class="rj-checkout-panel__header">
				<span><?php esc_html_e( 'مرحله نهایی', 'rezajordaan' ); ?></span>
				<h2 id="order_review_heading"><?php esc_html_e( 'مرور و پرداخت سفارش', 'rezajordaan' ); ?></h2>
				<p><?php esc_html_e( 'روش ارسال و درگاه پرداخت دلخواهتان را انتخاب کنید.', 'rezajordaan' ); ?></p>
			</header>

			<?php do_action( 'woocommerce_checkout_before_order_review' ); ?>

			<div id="order_review" class="woocommerce-checkout-review-order">
				<?php do_action( 'woocommerce_checkout_order_review' ); ?>
			</div>

			<?php do_action( 'woocommerce_checkout_after_order_review' ); ?>
		</aside>
	</div>
</form>

<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>
