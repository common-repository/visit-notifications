<?php
declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * @var WP_Post|WP_Term $object
 *
 * @var array $visitor
 *  @var int $visitor['time'] Unix Timestamp
 *  @var string $visitor['user_agent'] Browser responded User Agent
 *  @var string $visitor['referer'] Browser reported Referer
 *  @var string $visitor['ip_addr'] Annoyimsed IP Address
 *  @var string $visitor['location'] IP reported Location
 *  @var string $visitor['timezone'] IP reported timezone
 */

if ( $object instanceof WP_Post ) {
	$href = get_permalink( $object->ID );
} elseif ( $object instanceof WP_Term ) {
	$href = get_term_link( $object->term_id );
}
?>

<h2>New Visitor on <a href='<?php echo esc_url( $href ); ?>'><?php echo esc_html( $object->post_title ?? $object->name ); ?></a></h2>
<ul>
	<li><strong>Access Time: </strong><?php echo esc_html( date( 'd/m/Y H:i', $visitor['time'] ) ); ?></li>
	<li><strong>User Agent: </strong><?php echo esc_html( $visitor['user_agent'] ); ?></li>

	<?php if ( isset( $visitor['referer'] ) ) : ?>
		<li><strong>Referer: </strong><?php echo esc_html( $visitor['referer'] ); ?></li>
	<?php endif; ?>

	<?php if ( isset( $visitor['ip_addr'] ) ) : ?>
		<li><strong>IP Address: </strong><?php echo esc_html( $visitor['ip_addr'] ); ?></li>
	<?php endif; ?>

	<?php if ( isset( $visitor['location'] ) ) : ?>
		<li><strong>Location: </strong><?php echo esc_html( $visitor['location'] ); ?></li>
	<?php endif; ?>

	<?php if ( isset( $visitor['timezone'] ) ) : ?>
		<li><strong>Timezone: </strong><?php echo esc_html( $visitor['timezone'] ); ?></li>
	<?php endif; ?>
</ul>

