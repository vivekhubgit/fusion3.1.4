<?php
/* $Id$ */
/*
	call.php
	Copyright (C) 2008, 2009 Mark J Crane
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/
include "root.php";
require_once "includes/require.php";
require_once "includes/checkauth.php";
if (permission_exists('click_to_call_view')) {
	//access granted
}
else {
	echo "access denied";
	exit;
}

require_once "includes/header.php";

if (is_array($_REQUEST) && !empty($_REQUEST['src']) && !empty($_REQUEST['dest'])) {
	//get the http variables and set them as variables
		$src = $_REQUEST['src'];
		$dest = $_REQUEST['dest'];
		$ringback = $_REQUEST['ringback'];
		$src = str_replace(array('.', '(', ')', '-', ' '), '', $src);
		$dest = str_replace(array('.', '(', ')', '-', ' '), '', $dest);
		$src_cid_name = $_REQUEST['src_cid_name'];
		$src_cid_number = $_REQUEST['src_cid_number'];
		$dest_cid_name = $_REQUEST['dest_cid_name'];
		$dest_cid_number = $_REQUEST['dest_cid_number'];
		$rec = $_REQUEST['rec']; //true,false
		if (strlen($cid_number) == 0) { $cid_number = $src;}
		if (strlen($_SESSION['context']) > 0) {
			$context = $_SESSION['context'];
		}
		else {
			$context = 'default';
		}

	//translate ringback
		switch ($ringback) {
			case "us-ring":
				$ringback_value = "\'%(2000,4000,440.0,480.0)\'";
				break;
			case "uk-ring":
				$ringback_value = "\'%(400,200,400,450);%(400,2200,400,450)\'";
				break;
			case "fr-ring":
				$ringback_value = "\'%(1500,3500,440.0,0.0)\'";
				break;
			case "rs-ring":
				$ringback_value = "\'%(1000,4000,425.0,0.0)\'";
				break;
			case "music":
				$ringback_value = "\'local_stream://moh\'";
				break;
			default:
				$ringback = 'us-ring';
				$ringback_value = "\'%(2000,4000,440.0,480.0)\'";
		}

	//source should see the destination caller id
		if (strlen($src) < 7) {
			$source = "{origination_caller_id_name='$src_cid_name',origination_caller_id_number=$src_cid_number,instant_ringback=true,ringback=$ringback_value,presence_id=$src@".$_SESSION['domains'][$domain_uuid]['domain_name'].",call_direction=outbound}sofia/internal/$src%".$_SESSION['domains'][$domain_uuid]['domain_name'];
		}
		else {
			$bridge_array = outbound_route_to_bridge ($_SESSION['domain_uuid'], $src);
			$source = "{origination_caller_id_name='$src_cid_name',origination_caller_id_number=$src_cid_number,instant_ringback=true,ringback=$ringback_value,presence_id=$src@".$_SESSION['domains'][$domain_uuid]['domain_name'].",call_direction=outbound}".$bridge_array[0];
		}

	//destination needs to see the source caller id
		if (strlen($dest) < 7) {
			$switch_cmd = "api originate $source &transfer('".$dest." XML ".$context."')";
		}
		else {
			if (strlen($src) < 7) {
				if (strlen($dest_cid_number) == 0) {
					//get the caller id from the extension caller id comes from the extension (the source number)
						$sql = "";
						$sql .= "select * from v_extensions ";
						$sql .= "where domain_uuid = '$domain_uuid' ";
						$sql .= "and extension = '$src' ";
						$prep_statement = $db->prepare(check_sql($sql));
						$prep_statement->execute();
						$result = $prep_statement->fetchAll(PDO::FETCH_NAMED);
						foreach ($result as &$row) {
							$dest_cid_name = $row["outbound_caller_id_name"];
							$dest_cid_number = $row["outbound_caller_id_number"];
							break; //limit to 1 row
						}
						unset ($prep_statement);
				}
			}
			$bridge_array = outbound_route_to_bridge ($_SESSION['domain_uuid'], $dest);
			$destination = "{origination_caller_id_name='$dest_cid_name',origination_caller_id_number=$dest_cid_number}".$bridge_array[0];
			if (permission_exists('click_to_call_call')) {
				$switch_cmd = "api originate $source &bridge($destination)";
			}
		}

	//display the last command
		echo "<div align='center'>\n";
		echo "<table width='40%'>\n";
		echo "<tr>\n";
		echo "<th align='left'>Message</th>\n";
		echo "</tr>\n";
		echo "<tr>\n";
		echo "<td class='row_style1'><strong>$switch_cmd $src has called $dest</strong></td>\n";
		echo "</tr>\n";
		echo "</table>\n";
		echo "</div>\n";

	//create the even socket connection and send the event socket command
		$fp = event_socket_create($_SESSION['event_socket_ip_address'], $_SESSION['event_socket_port'], $_SESSION['event_socket_password']);
		if (!$fp) {
			//show the error message
				$msg = "<div align='center'>Connection to Event Socket failed.<br /></div>"; 
				echo "<div align='center'>\n";
				echo "<table width='40%'>\n";
				echo "<tr>\n";
				echo "	<th align='left'>Message</th>\n";
				echo "</tr>\n";
				echo "<tr>\n";
				echo "	<td class='row_style1'><strong>$msg</strong></td>\n";
				echo "</tr>\n";
				echo "</table>\n";
				echo "</div>\n";
		}
		else {
			//show the command result
				$result = trim(event_socket_request($fp, $switch_cmd));
				if (substr($result, 0,3) == "+OK") {
					$uuid = substr($result, 4);
					if ($rec == "true") {
						//use the server's time zone to ensure it matches the time zone used by freeswitch
							date_default_timezone_set($_SESSION['time_zone']['system']);
						//create the api record command and send it over event socket
							$switch_cmd = "api uuid_record ".$uuid." start ".$_SESSION['switch']['recordings']['dir']."/archive/".date("Y")."/".date("M")."/".date("d")."/".$uuid.".wav";
							$result2 = trim(event_socket_request($fp, $switch_cmd));
					}
				}
				echo "<div align='center'>\n";
				echo "<br />\n";
				echo $result;
				echo "<br />\n";
				echo "<br />\n";
				echo "</div>\n";
		}
}

//show html form
	echo "	<table width=\"100%\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\n";
	echo "	<tr>\n";
	echo "	<td align='left'>\n";
	echo "		<span class=\"vexpl\" class=\"red\">\n";
	echo "			<strong>Click to Call</strong>\n";
	echo "		</span>\n";
	echo "	</td>\n";
	echo "	<td align='right'>\n";
	echo "		&nbsp;\n";
	echo "	</td>\n";
	echo "	</tr>\n";
	echo "	<tr>\n";
	echo "	<td align='left' colspan='2'>\n";
	echo "		<span class=\"vexpl\">\n";
	echo "			Provide the following information to make a call from the source number to the destination number.\n";
	echo "		</span>\n";
	echo "	</td>\n";
	echo "\n";
	echo "	</tr>\n";
	echo "	</table>";

	echo "	<br />";

	echo "<form>\n";
	echo "<table border='0' width='100%' cellpadding='6' cellspacing='0'\n";
	echo "<tr>\n";
	echo "	<td class='vncellreq' width='40%'>Source Caller ID Name:</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<input name=\"src_cid_name\" value='$src_cid_name' class='formfld'>\n";
	echo "		<br />\n";
	echo "		Enter the name to show to the source caller.\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncellreq'>Source Caller ID Number:</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<input name=\"src_cid_number\" value='$src_cid_number' class='formfld'>\n";
	echo "		<br />\n";
	echo "		Enter the number to show to the source caller.\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncell' width='40%'>Destination Caller ID Name:</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<input name=\"dest_cid_name\" value='$dest_cid_name' class='formfld'>\n";
	echo "		<br />\n";
	echo "		Enter the name to send to the destination callee.\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncell'>Destination Caller ID Number:</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<input name=\"dest_cid_number\" value='$dest_cid_number' class='formfld'>\n";
	echo "		<br />\n";
	echo "		Enter the number to show to the destination callee.\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncellreq'>Source Number:</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<input name=\"src\" value='$src' class='formfld'>\n";
	echo "		<br />\n";
	echo "		Enter the number to call from.\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncellreq'>Destination Number:</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<input name=\"dest\" value='$dest' class='formfld'>\n";
	echo "		<br />\n";
	echo "		Enter the number to call.\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap>\n";
	echo "    Record:\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "    <select class='formfld' name='rec'>\n";
	echo "    <option value=''></option>\n";
	if ($rec == "true") { 
		echo "    <option value='true' selected='selected'>true</option>\n";
	}
	else {
		echo "    <option value='true'>true</option>\n";
	}
	if ($rec == "false") { 
		echo "    <option value='false' selected='selected'>false</option>\n";
	}
	else {
		echo "    <option value='false'>false</option>\n";
	}
	echo "    </select>\n";
	echo "<br />\n";
	echo "Select whether to record the call.\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' align='left' nowrap>\n";
	echo "    Ring Back:\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "    <select class='formfld' name='ringback'>\n";
	echo "    <option value=''></option>\n";
	if ($ringback == "us-ring") { 
		echo "    <option value='us-ring' selected='selected'>us-ring</option>\n";
	}
	else {
		echo "    <option value='us-ring'>us-ring</option>\n";
	}
	if ($ringback == "fr-ring") { 
		echo "    <option value='fr-ring' selected='selected'>fr-ring</option>\n";
	}
	else {
		echo "    <option value='fr-ring'>fr-ring</option>\n";
	}
	if ($ringback == "uk-ring") { 
		echo "    <option value='uk-ring' selected='selected'>uk-ring</option>\n";
	}
	else {
		echo "    <option value='uk-ring'>uk-ring</option>\n";
	}
	if ($ringback == "rs-ring") { 
		echo "    <option value='rs-ring' selected='selected'>rs-ring</option>\n";
	}
	else {
		echo "    <option value='rs-ring'>rs-ring</option>\n";
	}
	if ($ringback == "music") { 
		echo "    <option value='music' selected='selected'>music</option>\n";
	}
	else {
		echo "    <option value='music'>music</option>\n";
	}
	echo "    </select>\n";
	echo "<br />\n";
	echo "Defines what the caller will hear while destination is being called. The choices are music (music on hold) ring (ring tone.)\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td colspan='2' align='right'>\n";
	echo "		<input type=\"submit\" class='btn' value=\"Call\">\n";
	echo "	</td>\n";
	echo "</tr>\n";
	echo "</table>\n";
	echo "</form>";

//show the footer
	require_once "includes/footer.php";
?>
