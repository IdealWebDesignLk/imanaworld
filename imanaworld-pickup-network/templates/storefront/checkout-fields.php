<?php
defined( 'ABSPATH' ) || exit;
/** @var WC_Checkout $checkout */
/** @var object|null $branch */
?>
<div class="ipn-checkout-fields">
	<?php wp_nonce_field( 'ipn_checkout_fields', 'ipn_checkout_fields_nonce' ); ?>

	<?php if ( ! $branch ) : ?>

		<p class="ipn-checkout-fields__notice">
			<?php esc_html_e( 'No Click & Collect branch selected. Please choose a branch before checking out.', 'ipn' ); ?>
		</p>

	<?php else : ?>

		<?php if ( ! IPN_Branch::is_open_now( $branch->id ) ) : ?>
			<p class="ipn-checkout-fields__notice ipn-checkout-fields__notice--warn">
				<?php
				printf(
					/* translators: %s: branch name */
					esc_html__( '%s is currently closed. You can still place this order — preparation will start once the branch reopens.', 'ipn' ),
					esc_html( $branch->name )
				);
				?>
			</p>
		<?php endif; ?>

		<h3 class="ipn-checkout-fields__heading"><?php esc_html_e( 'Collection type', 'ipn' ); ?></h3>

		<label class="ipn-collect-option" for="ipn_collection_type_standard">
			<input type="radio" id="ipn_collection_type_standard" name="ipn_collection_type" value="standard" checked="checked" />
			<span class="ipn-collect-option__body">
				<span class="ipn-collect-option__title"><?php esc_html_e( 'Standard Collection', 'ipn' ); ?></span>
				<span class="ipn-collect-option__desc">
					<?php
					printf(
						/* translators: %s: standard preparation time, e.g. "1h 30m" */
						esc_html__( 'Normal preparation priority. No extra charge. Usually ready within %s.', 'ipn' ),
						esc_html( IPN_Checkout::format_minutes( $branch->standard_prep_minutes ) )
					);
					?>
				</span>
			</span>
		</label>

		<?php if ( ! empty( $branch->express_enabled ) ) : ?>
			<label class="ipn-collect-option" for="ipn_collection_type_express">
				<input type="radio" id="ipn_collection_type_express" name="ipn_collection_type" value="express" />
				<span class="ipn-collect-option__body">
					<span class="ipn-collect-option__title">
						<?php esc_html_e( 'Express Collection', 'ipn' ); ?>
						<span class="ipn-chip ipn-chip--express">
							<?php
							printf(
								/* translators: %s: express surcharge amount, formatted with the store's currency */
								esc_html__( '+ %s', 'ipn' ),
								esc_html( wp_strip_all_tags( wc_price( $branch->express_surcharge ) ) )
							);
							?>
						</span>
					</span>
					<span class="ipn-collect-option__desc">
						<?php
						printf(
							/* translators: %s: express preparation time, e.g. "1 hour" */
							esc_html__( 'Priority preparation — branch commits to having it ready within %s.', 'ipn' ),
							esc_html( IPN_Checkout::format_minutes( $branch->express_prep_minutes ) )
						);
						?>
					</span>
				</span>
			</label>
		<?php endif; ?>

		<h3 class="ipn-checkout-fields__heading"><?php esc_html_e( 'Nominate a recipient', 'ipn' ); ?></h3>

		<label class="ipn-recipient-toggle" for="ipn_recipient_toggle">
			<input type="checkbox" id="ipn_recipient_toggle" name="ipn_recipient_enabled" value="1" />
			<?php esc_html_e( 'Someone else will collect this order for me', 'ipn' ); ?>
		</label>

		<div class="ipn-recipient-fields" id="ipn-recipient-fields">
			<p class="form-row form-row-wide">
				<label for="ipn_recipient_name"><?php esc_html_e( 'Recipient full name', 'ipn' ); ?></label>
				<input
					type="text"
					class="input-text"
					id="ipn_recipient_name"
					name="ipn_recipient_name"
					placeholder="<?php esc_attr_e( 'e.g. Mpho Ramotswe', 'ipn' ); ?>"
				/>
			</p>
			<p class="form-row form-row-wide">
				<label for="ipn_recipient_phone"><?php esc_html_e( 'Recipient phone number', 'ipn' ); ?></label>
				<input
					type="text"
					class="input-text"
					id="ipn_recipient_phone"
					name="ipn_recipient_phone"
					placeholder="<?php esc_attr_e( 'e.g. +267 71 234 567', 'ipn' ); ?>"
				/>
			</p>
			<p class="form-row form-row-wide">
				<label for="ipn_recipient_id_number">
					<?php esc_html_e( 'Recipient ID number', 'ipn' ); ?>
					<span class="ipn-field-optional"><?php esc_html_e( '(optional)', 'ipn' ); ?></span>
				</label>
				<input
					type="text"
					class="input-text"
					id="ipn_recipient_id_number"
					name="ipn_recipient_id_number"
					placeholder="<?php esc_attr_e( 'Omang / passport number', 'ipn' ); ?>"
				/>
			</p>
		</div>

	<?php endif; ?>
</div>
