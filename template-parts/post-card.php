<?php
/**
 * Article card used by blog and archive views.
 *
 * @package RezaJordaan
 */
?>
<article <?php post_class( 'post-card' ); ?>>
	<a class="post-card__image" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
		<?php if ( has_post_thumbnail() ) : ?>
			<?php the_post_thumbnail( 'large', array( 'loading' => 'lazy' ) ); ?>
		<?php else : ?>
			<span>Reza Jordaan</span>
		<?php endif; ?>
	</a>
	<div class="post-card__body">
		<p class="post-card__meta"><?php echo esc_html( get_the_date() ); ?></p>
		<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
		<div class="post-card__excerpt"><?php the_excerpt(); ?></div>
		<a class="post-card__more" href="<?php the_permalink(); ?>"><?php esc_html_e( 'ادامه مطلب', 'rezajordaan' ); ?> <span aria-hidden="true">←</span></a>
	</div>
</article>
