<?php
defined( 'ABSPATH' ) || exit;
?>

<?php if ( ! empty( $customer_name ) ) : ?>
	<p><?php echo esc_html( sprintf( __( 'Hello %s,', 'woocommerce-cart-recovery' ), $customer_name ) ); ?></p>
<?php endif; ?>

<?php echo wpautop( wp_kses_post( $body ) ); ?>

<?php if ( ! empty( $coupon_code ) && ! empty( $discount_text ) ) : ?>
	<p>
		<?php
		echo esc_html(
			sprintf(
				/* translators: %s: discount label */
				__( 'Complete your order with %s.', 'woocommerce-cart-recovery' ),
				$discount_text
			)
		);
		?>
	</p>
<?php endif; ?>

<?php if ( ! empty( $cart_items ) ) : ?>
	<h2><?php esc_html_e( 'Your cart summary', 'woocommerce-cart-recovery' ); ?></h2>
	<table cellspacing="0" cellpadding="6" style="width:100%;border:1px solid #e5e5e5;" border="1">
		<thead>
			<tr>
				<th scope="col" style="text-align:left;"><?php esc_html_e( 'Product', 'woocommerce-cart-recovery' ); ?></th>
				<th scope="col" style="text-align:left;"><?php esc_html_e( 'Quantity', 'woocommerce-cart-recovery' ); ?></th>
				<th scope="col" style="text-align:left;"><?php esc_html_e( 'Total', 'woocommerce-cart-recovery' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $cart_items as $cart_item ) : ?>
				<tr>
					<td style="text-align:left;vertical-align:middle;"><?php echo esc_html( $cart_item['name'] ); ?></td>
					<td style="text-align:left;vertical-align:middle;"><?php echo esc_html( $cart_item['quantity'] ); ?></td>
					<td style="text-align:left;vertical-align:middle;"><?php echo wp_kses_post( $cart_item['total'] ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
		<tfoot>
			<tr>
				<th scope="row" colspan="2" style="text-align:left;"><?php esc_html_e( 'Total', 'woocommerce-cart-recovery' ); ?></th>
				<td style="text-align:left;"><?php echo wp_kses_post( $cart_total ); ?></td>
			</tr>
			<?php if ( ! empty( $coupon_code ) ) : ?>
				<tr>
					<th scope="row" colspan="2" style="text-align:left;"><?php esc_html_e( 'Discount code', 'woocommerce-cart-recovery' ); ?></th>
					<td style="text-align:left;"><strong><?php echo esc_html( $coupon_code ); ?></strong><?php if ( ! empty( $discount_text ) ) : ?><?php echo esc_html( ' (' . $discount_text . ')' ); ?><?php endif; ?></td>
				</tr>
			<?php endif; ?>
		</tfoot>
	</table>
<?php endif; ?>

<p style="margin-top:24px;">
	<a href="<?php echo esc_url( $recovery_url ); ?>" class="button">
		<?php esc_html_e( 'Recover your cart', 'woocommerce-cart-recovery' ); ?>
	</a>
</p>

<p><small><?php echo esc_html( $site_name ); ?></small></p>
