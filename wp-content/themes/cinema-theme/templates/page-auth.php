<?php
/*
Template Name: Auth
*/

get_header();

$auth_error = cinema_theme_get_auth_error();
?>
<main class="cinema-shell">
	<section class="cinema-page-hero">
		<div>
			<p class="cinema-kicker"><?php esc_html_e('Customer Access', 'cinema-theme'); ?></p>
			<h1><?php esc_html_e('Login or create an account to keep your tickets synced.', 'cinema-theme'); ?></h1>
		</div>
	</section>

	<?php if ($auth_error) : ?>
		<div class="cinema-alert"><?php echo esc_html($auth_error); ?></div>
	<?php endif; ?>

	<div class="cinema-auth-grid">
		<section class="cinema-booking-panel">
			<h2><?php esc_html_e('Login', 'cinema-theme'); ?></h2>
			<form method="post" class="cinema-form-stack">
				<?php wp_nonce_field('cinema_theme_auth', 'cinema_auth_nonce'); ?>
				<input type="hidden" name="cinema_auth_action" value="login">
				<label>
					<?php esc_html_e('Username or email', 'cinema-theme'); ?>
					<input type="text" name="log" required>
				</label>
				<label>
					<?php esc_html_e('Password', 'cinema-theme'); ?>
					<input type="password" name="pwd" required>
				</label>
				<label class="cinema-checkbox">
					<input type="checkbox" name="rememberme" value="1">
					<span><?php esc_html_e('Keep me signed in', 'cinema-theme'); ?></span>
				</label>
				<button type="submit" class="cinema-cta cinema-cta-block"><?php esc_html_e('Login', 'cinema-theme'); ?></button>
			</form>
		</section>

		<section class="cinema-booking-panel">
			<h2><?php esc_html_e('Register', 'cinema-theme'); ?></h2>
			<form method="post" class="cinema-form-stack">
				<?php wp_nonce_field('cinema_theme_auth', 'cinema_auth_nonce'); ?>
				<input type="hidden" name="cinema_auth_action" value="register">
				<label>
					<?php esc_html_e('Username', 'cinema-theme'); ?>
					<input type="text" name="username" required>
				</label>
				<label>
					<?php esc_html_e('Email', 'cinema-theme'); ?>
					<input type="email" name="email" required>
				</label>
				<label>
					<?php esc_html_e('Password', 'cinema-theme'); ?>
					<input type="password" name="password" required minlength="8">
				</label>
				<button type="submit" class="cinema-cta cinema-cta-block"><?php esc_html_e('Create Account', 'cinema-theme'); ?></button>
			</form>
		</section>
	</div>
</main>
<?php
get_footer();
