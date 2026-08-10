<?php
defined( 'ABSPATH' ) || exit;
/** @var array $branches */

$ipn_today = (int) current_time( 'w' );
?>
<div class="ipn-branch-selector">
	<h2 class="ipn-branch-selector__title"><?php esc_html_e( 'Choose your Choppies branch', 'ipn' ); ?></h2>
	<p class="ipn-branch-selector__sub">
		<?php esc_html_e( "Pick where you'd like to collect your order. We'll only show products in stock at that branch.", 'ipn' ); ?>
	</p>

	<?php if ( empty( $branches ) ) : ?>
		<p class="ipn-branch-selector__empty"><?php esc_html_e( 'No branches are currently available for Click & Collect.', 'ipn' ); ?></p>
	<?php else : ?>
		<div class="ipn-branch-grid">
			<?php foreach ( $branches as $ipn_branch ) : ?>
				<?php
				$ipn_is_open     = IPN_Branch::is_open_now( $ipn_branch->id );
				$ipn_hours_today = null;

				foreach ( IPN_Branch::get_hours( $ipn_branch->id ) as $ipn_hours_row ) {
					if ( (int) $ipn_hours_row->day_of_week === $ipn_today ) {
						$ipn_hours_today = $ipn_hours_row;
						break;
					}
				}

				$ipn_select_url = add_query_arg(
					array(
						'ipn_branch' => $ipn_branch->id,
						'_wpnonce'   => wp_create_nonce( 'ipn_select_branch_' . $ipn_branch->id ),
					)
				);

				// Every active branch stays selectable regardless of the
				// open-now status below — Click & Collect orders can be
				// placed for later collection even while a branch is
				// currently closed, and operating hours may not be
				// configured yet during pilot rollout. The pill is
				// informational only, unlike the mockup's disabled state.
				?>
				<a class="ipn-branch-card" href="<?php echo esc_url( $ipn_select_url ); ?>">
					<div class="ipn-branch-card__top">
						<div>
							<div class="ipn-branch-card__name"><?php echo esc_html( $ipn_branch->name ); ?></div>
							<?php if ( ! empty( $ipn_branch->address ) ) : ?>
								<div class="ipn-branch-card__address"><?php echo esc_html( $ipn_branch->address ); ?></div>
							<?php endif; ?>
						</div>
						<span class="ipn-status-pill <?php echo $ipn_is_open ? 'ipn-status-pill--open' : 'ipn-status-pill--closed'; ?>">
							<?php echo $ipn_is_open ? esc_html__( 'Open now', 'ipn' ) : esc_html__( 'Closed', 'ipn' ); ?>
						</span>
					</div>
					<div class="ipn-branch-card__hours">
						<?php
						if ( $ipn_hours_today && empty( $ipn_hours_today->is_closed ) && $ipn_hours_today->open_time && $ipn_hours_today->close_time ) {
							printf(
								/* translators: 1: today's opening time, 2: today's closing time */
								esc_html__( 'Today %1$s–%2$s', 'ipn' ),
								esc_html( date_i18n( 'H:i', strtotime( $ipn_hours_today->open_time ) ) ),
								esc_html( date_i18n( 'H:i', strtotime( $ipn_hours_today->close_time ) ) )
							);
						} else {
							esc_html_e( 'Hours not set', 'ipn' );
						}
						?>
					</div>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
