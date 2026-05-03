<?php

get_header();
?>
<main class="cinema-shell">
	<section class="cinema-page-hero">
		<div>
			<p class="cinema-kicker"><?php esc_html_e('Cinema Experience', 'cinema-theme'); ?></p>
			<h1><?php esc_html_e('Built for movie discovery and booking.', 'cinema-theme'); ?></h1>
		</div>
	</section>

	<section class="cinema-panel-stack">
		<?php if (have_posts()) : ?>
			<?php while (have_posts()) : the_post(); ?>
				<article class="cinema-copy-card">
					<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
					<div><?php the_excerpt(); ?></div>
				</article>
			<?php endwhile; ?>
		<?php else : ?>
			<p><?php esc_html_e('No content found.', 'cinema-theme'); ?></p>
		<?php endif; ?>
	</section>
</main>
<?php
get_footer();
