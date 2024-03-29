<?php
/*
 FusionPBX
 Version: MPL 1.1

 The contents of this file are subject to the Mozilla Public License Version
 1.1 (the "License"); you may not use this file except in compliance with
 the License. You may obtain a copy of the License at
 http://www.mozilla.org/MPL/

 Software distributed under the License is distributed on an "AS IS" basis,
 WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
 for the specific language governing rights and limitations under the
 License.

 The Original Code is FusionPBX

 The Initial Developer of the Original Code is
 Mark J Crane <markjcrane@fusionpbx.com>
 Portions created by the Initial Developer are Copyright (C) 2008-2012
 the Initial Developer. All Rights Reserved.

 Contributor(s):
 Mark J Crane <markjcrane@fusionpbx.com>
*/
require_once "root.php";
require_once "includes/require.php";
require_once "includes/checkauth.php";
if (permission_exists('domain_add') || permission_exists('domain_edit')) {
	//access granted
}
else {
	echo "access denied";
	exit;
}

//action add or update
	if (isset($_REQUEST["id"])) {
		$action = "update";
		$domain_uuid = check_str($_REQUEST["id"]);
	}
	else {
		$action = "add";
	}

//get http post variables and set them to php variables
	if (count($_POST)>0) {
		$domain_name = check_str($_POST["domain_name"]);
		$domain_description = check_str($_POST["domain_description"]);
	}

if (count($_POST) > 0 && strlen($_POST["persistformvar"]) == 0) {

	$msg = '';
	if ($action == "update") {
		$domain_uuid = check_str($_POST["domain_uuid"]);
	}

	//check for all required data
		//if (strlen($domain_name) == 0) { $msg .= "Please provide: Domain<br>\n"; }
		//if (strlen($domain_description) == 0) { $msg .= "Please provide: Description<br>\n"; }
		if (strlen($msg) > 0 && strlen($_POST["persistformvar"]) == 0) {
			require_once "includes/header.php";
			require_once "includes/persistformvar.php";
			echo "<div align='center'>\n";
			echo "<table><tr><td>\n";
			echo $msg."<br />";
			echo "</td></tr></table>\n";
			persistformvar($_POST);
			echo "</div>\n";
			require_once "includes/footer.php";
			return;
		}

	//add or update the database
		if ($_POST["persistformvar"] != "true") {
			if ($action == "add" && permission_exists('domain_add')) {
				$sql = "select count(*) as num_rows from v_domains ";
				$sql .= "where domain_name = '$domain_name' ";
				$prep_statement = $db->prepare($sql);
				if ($prep_statement) {
				$prep_statement->execute();
					$row = $prep_statement->fetch(PDO::FETCH_ASSOC);
					if ($row['num_rows'] == 0) {
						$sql = "insert into v_domains ";
						$sql .= "(";
						$sql .= "domain_uuid, ";
						$sql .= "domain_name, ";
						$sql .= "domain_description ";
						$sql .= ")";
						$sql .= "values ";
						$sql .= "(";
						$sql .= "'".uuid()."', ";
						$sql .= "'$domain_name', ";
						$sql .= "'$domain_description' ";
						$sql .= ")";
						$db->exec(check_sql($sql));
						unset($sql);
					}
				}
			}

			if ($action == "update" && permission_exists('domain_edit')) {
				$sql = "update v_domains set ";
				$sql .= "domain_name = '$domain_name', ";
				$sql .= "domain_description = '$domain_description' ";
				$sql .= "where domain_uuid = '$domain_uuid' ";
				$db->exec(check_sql($sql));
				unset($sql);
			}

		//upgrade the domains
			require_once "core/upgrade/upgrade_domains.php";

		//clear the domains session array to update it
			unset($_SESSION["domains"]);
			unset($_SESSION["domain_uuid"]);
			unset($_SESSION["domain_name"]);
			unset($_SESSION['domain']);
			unset($_SESSION['switch']);

		//redirect the browser
			require_once "includes/header.php";
			echo "<meta http-equiv=\"refresh\" content=\"2;url=domains.php\">\n";
			echo "<div align='center'>\n";
			if ($action == "update") {
				echo "Update Complete\n";
			}
			if ($action == "add") {
				echo "Add Complete\n";
			}
			echo "</div>\n";
			require_once "includes/footer.php";
			return;
		} //if ($_POST["persistformvar"] != "true")
} //(count($_POST)>0 && strlen($_POST["persistformvar"]) == 0)

//pre-populate the form
	if (count($_GET)>0 && $_POST["persistformvar"] != "true") {
		$domain_uuid = $_GET["id"];
		$sql = "select * from v_domains ";
		$sql .= "where domain_uuid = '$domain_uuid' ";
		$prep_statement = $db->prepare(check_sql($sql));
		$prep_statement->execute();
		$result = $prep_statement->fetchAll(PDO::FETCH_NAMED);
		foreach ($result as &$row) {
			$domain_name = $row["domain_name"];
			$domain_description = $row["domain_description"];
		}
		unset ($prep_statement);
	}

//show the header
	require_once "includes/header.php";

//show the content
	echo "<div align='center'>";
	echo "<table width='100%' border='0' cellpadding='0' cellspacing=''>\n";
	echo "<tr class='border'>\n";
	echo "	<td align=\"left\">\n";
	echo "		<br>";

	echo "<form method='post' name='frm' action=''>\n";
	echo "<div align='center'>\n";
	echo "<table width='100%'  border='0' cellpadding='6' cellspacing='0'>\n";
	echo "<tr>\n";
	if ($action == "add") {
		echo "<td align='left' width='30%' nowrap='nowrap'><b>Domain Add</b></td>\n";
	}
	if ($action == "update") {
		echo "<td align='left' width='30%' nowrap='nowrap'><b>Domain Edit</b></td>\n";
	}
	echo "<td width='70%' align='right'><input type='button' class='btn' name='' alt='back' onclick=\"window.location='domains.php'\" value='Back'></td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td align='left' colspan='2'>\n";
	echo "Control the list of domains to manage.<br /><br />\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	Domain:\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='text' name='domain_name' maxlength='255' value=\"$domain_name\">\n";
	echo "<br />\n";
	echo "Enter the domain name.\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	Description:\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='text' name='domain_description' maxlength='255' value=\"$domain_description\">\n";
	echo "<br />\n";
	echo "Enter the description.\n";
	echo "</td>\n";
	echo "</tr>\n";
	echo "	<tr>\n";
	echo "		<td colspan='2' align='right'>\n";
	if ($action == "update") {
		echo "				<input type='hidden' name='domain_uuid' value='$domain_uuid'>\n";
	}
	echo "				<input type='submit' name='submit' class='btn' value='Save'>\n";
	echo "		</td>\n";
	echo "	</tr>";
	echo "</table>";
	echo "</form>";

	if ($action == "update") {
		require "domain_settings.php";
	}

	echo "	</td>";
	echo "	</tr>";
	echo "</table>";
	echo "</div>";

//include the footer
	require_once "includes/footer.php";
?>