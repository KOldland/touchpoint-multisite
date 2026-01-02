<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class NSS_ArrayToTable
{
	public $html;
    function __construct($data, $attr = "")
    {
		if(empty($data[0]))
		return;
		
		$html = "<div class='grassblade_table'><table ".$attr."><thead>";
		$html .= "<tr>";
		foreach($data[0] as $header_field => $value) {
			$th_class = preg_replace('/[^A-Za-z0-9\-]/', '', strtolower($value));
			$html .= "<th class='".$th_class."'>".__($header_field, "grassblade")."</th>";
		}
		$html .= "</tr></thead><tbody>";
		
		foreach($data as $k => $row) {
			$tr_class = ($k % 2 == 1)? 'tr_odd':'tr_even';
			$html .= "<tr class='".$tr_class."'>";
			foreach($row as $field) {
				$html .= "<td>".$field."</td>";
			}
			$html .= "</tr>";
		}
		$html .= "</tbody></table></div>";
		$this->html = $html;
	}
	function show() {
		echo $this->html;
	}
	function get() {
		return $this->html;
	}
}
