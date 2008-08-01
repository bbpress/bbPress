			</div>
		</div>
	</div>
	<div id="bbFooter">
		<p>
			<?php printf(
				__('Thank you for using <a href="%s">bbPress</a> | <a href="%s">Documentation</a> | <a href="%s">Development</a> | Version %s'),
				'http://bbpress.org/',
				'http://bbpress.org/documentation/',
				'http://trac.bbpress.org/',
				bb_get_option( 'version' )
			) ?>
		</p>
		<!--
			If you like showing off the fact that your server rocks
		-->
		<!--
		<p><?php global $bbdb; printf(__('This page generated in %s seconds, using %d queries'), bb_number_format_i18n(bb_timer_stop(), 2), $bbdb->num_queries); ?></p>
		-->
	</div>
	
	<?php do_action('bb_admin_footer'); ?>
</body>
</html>
