<?php
$list_package = get_post_meta( get_the_ID(), 'package_list', true );
if ( $list_package && isset( $list_package[0] ) && $list_package[0]['title'] ) {

	?>

	<div class="st-package">
		<div class="st_wrap_list_package">
			<div class="st_content_list_package">
				<table class="table-auto">
					<thead>
						<tr>
							<th class="center title_td">
								<span><strong><?php echo __( 'Name Vehicle', 'traveler-childtheme' ); ?></strong></span>
							</th>
							<th class="center title_td">
								<span><strong><?php echo __( 'Price', 'traveler-childtheme' ); ?></strong></span>
							</th>
						</tr>
					</thead>
					<?php foreach ( $list_package as $packed ) : ?>
						<?php if ( $packed['title'] ) : ?>
							<tbody>
								<tr>
									<td class="center"><?php echo trim( $packed['title'] ); ?></td>
									<td class="center"><?php echo TravelHelper::format_money( $packed['package_price_fixed'] ); ?></td>
								</tr>
							</tbody>

						<?php endif; ?>
					<?php endforeach; ?>
				</table>
			</div>
		</div>
	</div>
	<div class="st-hr large"></div>
	<?php
}
?>


<div class="st-description 2" id="st-description">

	<?php
	if ( isset( $title ) ) {
		echo '<h2 class="st-heading-section">' . esc_html( $title ) . '</h2>';
	} else {
		?>
		<h2 class="st-heading-section">
			<?php
			$get_posttype = get_post_type( get_the_ID() );
			switch ( $get_posttype ) {
				case 'st_hotel':
					echo __( 'About this hotel', 'traveler-childtheme' );
					break;
				case 'st_tours':
					echo __( 'About this tour', 'traveler-childtheme' );
					break;
				case 'st_cars':
					echo __( 'About this car', 'traveler-childtheme' );
					break;
				case 'st_rental':
					echo __( 'About this rental', 'traveler-childtheme' );
					break;
				case 'st_activity':
					echo __( 'About this activity', 'traveler-childtheme' );
					break;
				case 'hotel_room':
					echo __( 'About this room', 'traveler-childtheme' );
					break;
				default:
					echo __( 'About this hotel', 'traveler-childtheme' );
					break;
			}
			?>
		</h2>
		<?php
	}
	the_content();
	?>
</div>
