<div>
	<label for=""><?php _e( 'Forum Capabilities', 'bbpress' ); ?></label>

	<fieldset class="bbp-form capabilities">
		<legend><?php _e( 'Forum Capabilities', 'bbpress' ); ?></legend>

		<?php foreach ( bbp_get_capability_groups() as $group ) : ?>

			<dl class="bbp-user-capabilities">
				<dt><?php bbp_capability_group_title( $group ); ?></dt>

				<?php foreach ( bbp_get_capabilities_for_group( $group ) as $capability ) : ?>

					<dd>
						<label for="_bbp_<?php echo $capability; ?>">
							<input class="checkbox" type="checkbox" id="_bbp_<?php echo $capability; ?>" name="_bbp_<?php echo $capability; ?>" value="1" <?php checked( user_can( bbp_get_displayed_user_id(), $capability ) ); ?> tabindex="<?php bbp_tab_index(); ?>" />
							<?php bbp_capability_title( $capability ); ?>
						</label>
					</dd>

				<?php endforeach; ?>

			</dl>

		<?php endforeach; ?>
	</fieldset>
</div>

<div>
	<label for="bbp-default-caps"><?php _e( 'Reset', 'bbpress' ); ?></label>
	<label>
		<input class="checkbox" type="checkbox" id="bbp-default-caps" name="bbp-default-caps" tabindex="<?php bbp_tab_index(); ?>" />
		<?php _e( 'Reset forum capabilities to match the user role.', 'bbpress' ); ?>
	</label>
</div>

