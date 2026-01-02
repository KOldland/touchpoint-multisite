<?php if ( ! defined( 'ABSPATH' ) ) exit; 

	$gb_reports = apply_filters("grassblade/reports/js/gb_reports", array());
	echo gb_get_json_script('gb_reports', $gb_reports);

	$gb_report_bootstrap = apply_filters("grassblade/reports/js/functions", array());
	echo gb_get_json_script('GB_REPORTS_FUNCTIONS', $gb_report_bootstrap);

	$gb_reports_scripts = apply_filters("grassblade/reports/scripts", array());
	echo gb_get_scripts( $gb_reports_scripts );
?>
<div id="grassblade_reports">
	<div>
		<h2><?php _e("Generate Your Report", "grassblade"); 

			if(current_user_can("manage_options")) {
			?>
			<a href='<?php echo esc_url( admin_url("options-general.php?page=kh-xapi-settings") ); ?>'><i class="dashicons  dashicons-admin-settings" style="text-decoration: none;"></i></a>
			<a href='https://www.nextsoftwaresolutions.com/kb/reports-for-group-leaders-admins/' target="_blank"><i class="dashicons  dashicons-editor-help gb_no_underscore"></i></a>
			<?php } ?>
 		</h2>
		<table>
			<tr>
				<th><?php _e("Report", "grassblade"); ?></th>
				<td>
					<select id="nss_report" onchange="grassblade_report_selected();grassblade_option_selected(this, '', 'group');" name="report">
						<option value=""><?php _e("--- Select a Report ---", "grassblade"); ?></option>
						<?php 
						foreach ($available_reports as $report_key => $report_name) {
							?>
							<option value="<?php echo $report_key; ?>"><?php echo $report_name; ?></option>
							<?php 
						}
						?>
					</select>
				</td>
			</tr>
			<?php
				$report_filter_fields = array(
					"group" => array( "file" => dirname(__FILE__)."/filter_fields/group.php" ),
					"course" => array( "file" => dirname(__FILE__)."/filter_fields/course.php" ),
					"user" => array( "file" => dirname(__FILE__)."/filter_fields/user.php" ),
					"date_range" => array( "file" => dirname(__FILE__)."/filter_fields/date_range.php" ),
					"content" => array( "file" => dirname(__FILE__)."/filter_fields/content.php" ),
				);
				
				$report_filter_fields = apply_filters("grassblade/reports/filters/fields", $report_filter_fields);

				foreach($report_filter_fields as $report_field) {
					if(!empty($report_field["file"]) && file_exists($report_field["file"]))
					include_once ( $report_field["file"] );
					if(!empty($report_field["html"]))
					echo $report_field["html"];
				}
			?>
			<tr class="nss_report_submit report_options" style="display:none;">
				<td></td>
				<td>
					<input name="nss_report_submit" type="submit" value="Show Report" style="width: 59%; margin: 10px 0;" onClick="return grassblade_nss_show_report();" class="btn btn-green btn-smaller" />
					<input name="nss_report_submit" type="submit" value="Download Report as CSV" style="display:none; width: 59%; margin: 10px 0;"  />
				</td>
			</tr>
		</table>
	</div>

</div>

<div id="grassblade_reports_output_main" style='display:none'>
<h2><?php _e("Report", "grassblade"); ?></h2>
	<div id="buttons123" class="buttons123"></div>
	<div id="columns-list" class="columns-list"></div>
	<table id="grassblade_reports_output" class="display compact order-column hover stripe" style="width: 100%;" ></table>
</div>
