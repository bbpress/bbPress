		</div>
				
		<!-- 
			If you like showing off the fact that your server rocks,
			<h3><?php bb_timer_stop(1); ?> - <?php echo $bbdb->num_queries; ?> queries</h3> 
		-->

	</div>
	
	<div id="footer">
		<p><?php option('name'); ?> is proudly powered by <a href="http://bbpress.org">bbPress</a>.</p>
	</div>

	<?php do_action('bb_foot', ''); ?>

</body>
</html>
