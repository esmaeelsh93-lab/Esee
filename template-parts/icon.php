<?php
/**
 * Small inline icons used by benefit cards.
 *
 * @package RezaJordaan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$icon = isset( $args['icon'] ) ? $args['icon'] : 'heart';
?>
<svg aria-hidden="true" viewBox="0 0 48 48" fill="none">
	<?php if ( 'rocket' === $icon ) : ?>
		<path d="M27 8c7-3 12-2 13-1 1 1 2 6-1 13l-8 12-15-15L27 8Z"/>
		<path d="m16 17-7 2-4 6 11 1m15 6-2 7-6 4-1-11M14 31l-6 6m3-2-2 7 7-3m-3-9 5 5"/>
		<circle cx="32" cy="15" r="4"/>
	<?php elseif ( 'tag' === $icon ) : ?>
		<path d="M7 9v14l18 18 16-16L23 7H9a2 2 0 0 0-2 2Z"/>
		<circle cx="15" cy="15" r="3"/>
		<path d="m25 19 6 6m-7 5 2 2"/>
	<?php elseif ( 'sparkles' === $icon ) : ?>
		<path d="M24 5c1 10 5 14 15 15-10 1-14 5-15 15-1-10-5-14-15-15 10-1 14-5 15-15Z"/>
		<path d="M39 31c.5 5 2.5 7 7 7.5-4.5.5-6.5 2.5-7 7.5-.5-5-2.5-7-7-7.5 4.5-.5 6.5-2.5 7-7.5ZM9 3c.4 4 2 5.6 6 6-4 .4-5.6 2-6 6-.4-4-2-5.6-6-6 4-.4 5.6-2 6-6Z"/>
	<?php elseif ( 'bag' === $icon ) : ?>
		<path d="M9 17h30l-2 27H11L9 17Z"/>
		<path d="M17 19v-6a7 7 0 0 1 14 0v6"/>
		<path d="M18 27c1 4 3 6 6 6s5-2 6-6"/>
	<?php elseif ( 'chat' === $icon ) : ?>
		<path d="M6 8h36v27H23l-11 8 3-8H6V8Z"/>
		<path d="M14 18h20M14 25h13"/>
	<?php else : ?>
		<path d="M24 42C17 36 6 29 6 18 6 8 18 4 24 13 30 4 42 8 42 18c0 11-11 18-18 24Z"/>
		<path d="M15 19c1-3 3-5 6-5"/>
	<?php endif; ?>
</svg>
