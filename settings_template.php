<?php if(!defined("BACKUP2EMAIL")) die("Can't access to this file"); ?>

<div class="wrap">
	<?php screen_icon(); ?>
	<h2>Backup2Email settings:</h2>	
	<form method="post">
		<table class="admin_table">
    	<?php foreach ($plugoptions as $value) :
		if ($value['type'] == "checkbox") : ?>
			<tr>
			  <td><?php echo $value['name']; ?>:</td>
			  <td>
				  	<?php 
				  	if(get_option($value['id']))
				  		$checked = "checked='checked'";
				  	else 
				  		$checked = ""; 
				  	?>
					<input type="checkbox" name="<?php echo $value['id']; ?>" value="true" <?php echo $checked; ?> />
				</td>
			</tr>
		<?php elseif ($value['type'] == "text") : ?>
			<tr>
			  <td><?php echo $value['name']; ?>:</td>
			  <td><input name="<?php echo $value['id']; ?>" type="text" placeholder="<?php echo $value['placeholder']; ?>" value="<?php if ( get_option( $value['id'] ) != "") { echo htmlspecialchars(get_option( $value['id'] )); } else { echo $value['std']; } ?>" /></td>
			</tr>
		<?php elseif ($value['type'] == "selectbox") : ?>
			<tr>
				<td><?php echo $value['name']; ?>:</td>
				<td>
					<select name="<?php echo $value['id']; ?>">
						<?php foreach ($value['options'] as $key => $v) : ?>
						<?php $selected = (get_option($value['id']) == $key) ? "selected='selected'" : ""; ?>
						<option value="<?php echo $key; ?>" <?php echo $selected; ?>><?php echo $v; ?></option>
						<?php endforeach; ?>
					</select>
					Next backup time: <?php 
				$periodically = get_option($shortname."_backup_periodically");
$last_backup_date = get_option('last_backup_date');
if($last_backup_date) {
	add_option('last_backup_date', date('Y-m-d H:i:s'));
}
if($periodically) {
	echo date('Y-m-d H:i:s', strtotime($last_backup_date . " + 1 ".$periodically));
} else {
	echo 'Never';
}
?>				</td>
			</tr>
		<?php endif;
	endforeach;
		?>
		</table>
		<div class="submit">
			<input name="save" type="submit" class="button-primary" value="Save settings" />
			<input name="reset" type="submit" value="Reset form" />
		</div>
	</form>
</div>

<form method="post">
	<input name="backupnow" type="submit" class="button-primary" value="Backup Now" />
	&nbsp;&nbsp;<span>Last backup time: <?php echo get_option('last_backup_date') ? get_option('last_backup_date') : 'Never'; ?></span>
</form>