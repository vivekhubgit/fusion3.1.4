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
if (permission_exists('virtual_tables_delete')) {
	//access granted
}
else {
	echo "access denied";
	exit;
}

//get the http get variable
	if (count($_GET)>0) {
		$id = check_str($_GET["id"]);
	}

//show the header
	require_once "includes/header.php";

if (strlen($id)>0) {
	$sql = "";
	$sql .= "delete from v_virtual_tables ";
	$sql .= "where domain_uuid = '$domain_uuid' ";
	$sql .= "and virtual_table_uuid = '$id' ";
	$prep_statement = $db->prepare(check_sql($sql));
	$prep_statement->execute();
	unset($sql);
}

//redirect the user
	echo "<meta http-equiv=\"refresh\" content=\"2;url=v_virtual_tables.php\">\n";
	echo "<div align='center'>\n";
	echo "Delete Complete\n";
	echo "</div>\n";

//show the footer
	require_once "includes/footer.php";
	return;

?>
