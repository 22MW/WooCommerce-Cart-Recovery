<?php
defined( 'ABSPATH' ) || exit;

$text_align = is_rtl() ? 'right' : 'left';
?>

<div class="email-introduction">
	<?php if ( ! empty( $customer_name ) ) : ?>
		<p><?php echo esc_html( sprintf( /* translators: %s: customer name */ __( 'Hello %s,', 'vfwoo_woocommerce-cart-recovery' ), $customer_name ) ); ?></p>
	<?php endif; ?>

	<?php echo wpautop( wp_kses_post( $body ) ); ?>

	<?php if ( ! empty( $coupon_code ) && ! empty( $discount_text ) ) : ?>
		<p>
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: discount label */
					__( 'Complete your order with %s.', 'vfwoo_woocommerce-cart-recovery' ),
					$discount_text
				)
			);
			?>
		</p>
	<?php endif; ?>
</div>

<?php if ( ! empty( $cart_items ) ) : ?>
	<h2><?php esc_html_e( 'Your cart summary', 'vfwoo_woocommerce-cart-recovery' ); ?></h2>
	<div style="margin-bottom:24px;">
		<table class="td font-family email-order-details" cellspacing="0" cellpadding="6" style="width:100%;border:none;border-collapse:separate;" border="0">
			<thead>
				<tr>
					<th class="td" scope="col" style="text-align:<?php echo esc_attr( $text_align ); ?>;border:none;"><?php esc_html_e( 'Product', 'vfwoo_woocommerce-cart-recovery' ); ?></th>
					<th class="td" scope="col" style="text-align:right;border:none;"><?php esc_html_e( 'Quantity', 'vfwoo_woocommerce-cart-recovery' ); ?></th>
					<th class="td" scope="col" style="text-align:right;border:none;"><?php esc_html_e( 'Price', 'vfwoo_woocommerce-cart-recovery' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $cart_items as $cart_item ) : ?>
					<tr class="order_item">
						<td class="td font-family text-align-left" style="vertical-align:middle;word-wrap:break-word;border:none;">
							<table class="order-item-data" role="presentation">
								<tr>
									<?php if ( ! empty( $cart_item['image'] ) ) : ?>
										<td style="padding-<?php echo esc_attr( is_rtl() ? 'left' : 'right' ); ?>:12px;vertical-align:top;width:48px;">
											<?php echo wp_kses_post( $cart_item['image'] ); ?>
										</td>
									<?php endif; ?>
									<td style="vertical-align:top;">
										<strong style="font-size:inherit;font-weight:inherit;"><?php echo esc_html( $cart_item['name'] ); ?></strong>
										<?php if ( ! empty( $cart_item['meta'] ) ) : ?>
											<div class="email-order-item-meta"><?php echo wp_kses_post( $cart_item['meta'] ); ?></div>
										<?php endif; ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="td font-family text-align-right" style="vertical-align:middle;text-align:right;border:none;">
							<?php echo esc_html( (string) $cart_item['quantity'] ); ?>
						</td>
						<td class="td font-family text-align-right" style="vertical-align:middle;text-align:right;border:none;">
							<?php echo wp_kses_post( $cart_item['total'] ); ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<hr style="border:0;border-top:1px solid rgba(30,30,30,0.2);margin:20px 0;">
		<table class="td font-family email-order-details" cellspacing="0" cellpadding="6" style="width:100%;border:none;border-collapse:separate;" border="0">
			<tbody>
				<?php foreach ( $summary_totals as $total_row ) : ?>
					<tr class="order-totals">
						<th class="td text-align-left" scope="row" colspan="2" style="text-align:<?php echo esc_attr( $text_align ); ?>;border:none;">
							<?php echo esc_html( $total_row['label'] ); ?>
						</th>
						<td class="td text-align-right" style="text-align:right;border:none;">
							<?php echo wp_kses_post( $total_row['value'] ); ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>

<p style="margin-top:24px;text-align:center;">
	<a href="<?php echo esc_url( $recovery_url ); ?>" style="background-color:#000;color:#fff;padding:14px 28px;text-decoration:none;border-radius:4px;display:inline-block;font-weight:bold;">
		<?php esc_html_e( 'Recover your cart', 'vfwoo_woocommerce-cart-recovery' ); ?>
	</a>
</p>

<p><small><?php echo esc_html( $site_name ); ?></small></p>
