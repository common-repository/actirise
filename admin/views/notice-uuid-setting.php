<div class="notice notice-success">
	<p>
		<?php
			printf(
				/* translators: %s: link to settings url */
				wp_kses( __( 'Don’t miss to configure your Actirise plugin : <a href="%s">configure it !</a>', 'actirise' ), array( 'a' => array( 'href' => array() ) ) ),
				/** @var string $url */
				esc_url( $url )
			);
			?>
	</p>
</div>
