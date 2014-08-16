<div class="form-group">
	<label class="control-label" for="length"><?php echo __('Field length'); ?></label>
	<div class="controls">
		<?php echo Form::input( 'length', $field->length, array(
			'class' => 'input-mini', 'id' => 'length'
		) ); ?>
	</div>
</div>

<div class="form-group form-inline">
	<label class="control-label" for="min"><?php echo __('Min'); ?></label>
	<div class="controls">
		 <?php echo Form::input( 'min', $field->min, array(
			'class' => 'input-mini', 'id' => 'min'
		) ); ?>
		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
		<label for="max"><?php echo __('Max'); ?></label>
		&nbsp;&nbsp;&nbsp;
		<?php echo Form::input( 'max', $field->max, array(
			'class' => 'input-mini', 'id' => 'max'
		) ); ?>
	</div>
</div>

<hr />

<div class="form-group">
	<label class="control-label" for="primitive_default"><?php echo __( 'Default value' ); ?></label>
	<div class="controls">
		<?php
		echo Form::input( 'default', $field->default, array(
			'class' => 'input-xlarge', 'id' => 'primitive_default'
		) );
		?>
	</div>
</div>