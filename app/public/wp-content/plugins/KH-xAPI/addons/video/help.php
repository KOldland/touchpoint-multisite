<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<h2 name="video_tracking"><?php _e("Advanced Video Tracking", "grassblade"); ?></h2>

<a href="#video_tracking" onclick="return showHideOptional('grassblade_video_tracking');"><h3><img src="<?php echo get_bloginfo('wpurl')."/wp-content/plugins/grassblade/img/button.png"; ?>"/><span style="margin-left:10px;"><?php _e("What is Video Tracking?", "grassblade"); ?></span></h3></a>
<div id="grassblade_video_tracking"  class="infoblocks"  style="display:none;">
<p>
<?php _e("Video Tracking enables tracking video access, interaction and completion using xAPI.", "grassblade"); ?>  
</p>
<?php _e("you can track each and every activity of the user, like:", "grassblade"); ?>  
<p>
</p>
<ul>
	<li><?php _e(" 1. Play (played) ", "grassblade"); ?>  </li>
	<li><?php _e(" 2. Pause (paused) ", "grassblade"); ?>  </li>
	<li><?php _e(" 3. Completion (completed) ", "grassblade"); ?>  </li>
	<li><?php _e(" 4. Volume Change (interacted) ", "grassblade"); ?>  </li>
	<li><?php _e(" 5. Skipping (seeked) ", "grassblade"); ?>  </li>
	<li><?php _e(" 6. Full Screen (interacted)", "grassblade"); ?>  </li>
</ul>
<p>
<?php _e("This data will show on your LRS reports, and can also be use for completion tracking using GrassBlade's Completion Tracking feature.", "grassblade"); ?>  
</p>
<p>
<?php echo sprintf(__("Learn More: %s", "grassblade"), '<a href="https://www.nextsoftwaresolutions.com/kb/advanced-video-tracking/" target="_blank">Advanced Video Tracking</a>');  ?>
</p>
</div>
