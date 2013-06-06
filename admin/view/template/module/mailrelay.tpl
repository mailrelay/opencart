<?php echo $header; ?>
<div id="content">
<div class="breadcrumb">
  <?php foreach ($breadcrumbs as $breadcrumb) { ?>
  <?php echo $breadcrumb['separator']; ?><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a>
  <?php } ?>
</div>
<?php if ($error_warning) { ?>
<div class="warning"><?php echo $error_warning; ?></div>
<?php } ?>
<?php if ($success) { ?>
<div class="success"><?php echo $success; ?></div>
<?php } ?>
<div class="box">
  <div class="heading">
    <h1><img src="view/image/module.png" alt="" /> <?php echo $text_config; ?></h1>
    <div class="buttons"><a onclick="$('#formConfig').submit();" class="button"><span><?php echo $button_save; ?></span></a><a onclick="location = '<?php echo $cancel; ?>';" class="button"><span><?php echo $button_cancel; ?></span></a></div>
  </div>
  <div class="content">
    <form action="<?php echo $action_save; ?>" method="post" enctype="multipart/form-data" id="formConfig">
      <table class="form">
		<!-- hostname -->
        <tr>
          <td><?php echo $entry_hostname; ?></td>
          <td><input type="text" name="hostname" size="40" value="<?php echo $hostname; ?>" /></td>
        </tr>
		<!-- key -->
        <tr>
          <td><?php echo $entry_key; ?></td>
          <td><input type="text" name="key" size="50" value="<?php echo $key; ?>" /></td>
        </tr>
      </table>
      <!-- . -->
    </form>
  </div>
  <div class="heading">
    <h1><img src="view/image/module.png" alt="" /> <?php echo $text_sync; ?></h1>
    <div class="buttons"><a onclick="$('#formSync').submit();" class="button"><span><?php echo $text_start_sync; ?></span></a><a onclick="location = '<?php echo $cancel; ?>';" class="button"><span><?php echo $button_cancel; ?></span></a></div>
  </div>
  <div class="content">
    <form action="<?php echo $action_sync; ?>" method="post" enctype="multipart/form-data" id="formSync">
      <table class="form">
		<!-- groups -->
        <tr>
          <td><?php echo $entry_groups; ?></td>
          <td>
          <select name="last_group">
          <?php foreach ($groups as $groupId => $groupName) { ?>
          <?php if ($groupId == $last_group) { ?>
          <option value="<?php echo $groupId; ?>" selected="selected"><?php echo $groupName; ?></option>
          <?php } else { ?>
          <option value="<?php echo $groupId; ?>"><?php echo $groupName; ?></option>
          <?php } ?>
          <?php } ?>
        </select></td>
          </td>
        </tr>
      </table>
      <!-- . -->
    </form>
  </div>
</div>
<script type="text/javascript">
<!--

//-->
</script>
<?php echo $footer; ?>