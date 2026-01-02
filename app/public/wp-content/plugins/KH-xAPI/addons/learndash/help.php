<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<h2 name="xapi_learndash"><?php _e('LearnDash','grassblade'); ?></h2>
<a href="#xapi_learndash" onclick="return showHideOptional('grassblade_xapi_learndash_what_is_tracked');"><h3><img src="<?php echo get_bloginfo('wpurl')."/wp-content/plugins/grassblade/img/button.png"; ?>"/><span style="margin-left:10px;"><?php _e('What LearnDash related information is tracked by GrassBlade to the LRS?','grassblade'); ?></span></h3></a>
<div id="grassblade_xapi_learndash_what_is_tracked"  class="infoblocks"  style="display:none;">
<p>
<?php 
_e("GrassBlade tracks the following information related to LearnDash:<br>
	1. Attempt and Completion related to LearnDash Topics, Quizzes, Lessons and Courses. <br>
	2. LearnDash Quizzes answers and pass/fail. 
",'grassblade'); ?>

</p>
</div>

<a href="#xapi_learndash" onclick="return showHideOptional('grassblade_xapi_learndash_what_is_passed_back');"><h3><img src="<?php echo get_bloginfo('wpurl')."/wp-content/plugins/grassblade/img/button.png"; ?>"/><span style="margin-left:10px;"><?php _e('What information related to xAPI Content is passed back to LearnDash?','grassblade'); ?></span></h3></a>
<div id="grassblade_xapi_learndash_what_is_passed_back"  class="infoblocks"  style="display:none;">
<p>
<?php 
echo sprintf(__("If you are using GrassBlade LRS and have enabled Completion Tracking. Following information related to xAPI Content is passed back to LearnDash: <br>
	1. Completion <br>
	2. Pass/Fail <br>
	3. Quiz Score <br>
	<br>
	Quiz Score and Pass/Fail information is used when xAPI Content is added on a LearnDash Quiz page. And can be used to award LearnDash Certificates based on pass/fail or scores earned in xAPI Content. <br><br>
	Completion or Pass/Fail can be used to automatically mark LearnDash lesson, topic, or quiz as completed based on xAPI Content, and to allow learners to progress only after completing the xAPI Content. <br><br>
	The following article will help you setup Completion Tracking for LearnDash: <br> %s",'grassblade'), "<a href='https://www.nextsoftwaresolutions.com/kb/using-grassblade-completion-tracking-with-learndash/' target='_blank'>".__("Using Completion Tracking with LearnDash","grassblade")."</a>"); ?>

</p>
</div>
