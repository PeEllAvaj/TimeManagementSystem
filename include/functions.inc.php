<?php
session_start();

if(!isset($_GET["action"])) {
	$_GET["action"] = "";
}

include("constants.inc.php");
include("classes/classes.inc.php");

function showContent($content,  $pageTitle = "", $section = "") {

	if($pageTitle == "") {
		$pageTitle = "Time Management System - by Stephen Fluin";
	}
	
	//Primary Replacements:
	$replace["url"] = "http://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
	$replace["ip"] = $_SERVER["REMOTE_ADDR"];
	$replace["pageTitle"] = $pageTitle;
	$replace["content"] = $content;
	$replace["extraHead"] = $GLOBALS["extraHead"];
	$replace["navbar"] = getNavBar();
	if($_SESSION["userid"]) {
		$replace["logout"] = "<a href=\"logout.php\">Logout</a>";
	} else {
		$replace["logout"] = "";
	}
		
	print wrap("primary.html", $replace);
	exit;
		
}
function showPage($pageName, $pageTitle = "") {
	$filename = $GLOBALS["root_path"] . "pages/$pageName";
	$fileHandle = fopen( $filename, "r");
	if( !$fileHandle ) {
		$content = "Bad Page (pages/$pageName).";
	} else {
		$content = fread( $fileHandle, filesize( $filename ) );
		fclose($fileHandle);
	}
	
	showContent($content, $pageTitle);
}
function wrap( $templateName , $replace = array()) {

	
	$MASTER_PATH = getcwd();
	chdir( dirname(__FILE__) );
	$template = $GLOBALS["root_path"] . "templates/" . $templateName;
	
	$handle = fopen( $template , "r");
	if(!$handle) {
		return "Bad Template ( " . $template . ")!";
	}
	$tContents = fread( $handle, filesize( $template ) );
	fclose( $handle );
	
	foreach( $replace as $key => $value ) {
		if( substr( $key , 0 , 1 ) == "!" ) {

			$key = substr( $key, 1 );
		} else {
			$key = strtr( $key , array("/" => "\/") );
			$key = "/\[" . $key . "\]/";
		}
		$tContents = preg_replace( $key , $value, $tContents );

		
	}
	chdir($MASTER_PATH);
	return $tContents;
}


function getUsername( $userId ) {
	if($GLOBALS["memberList"][$userId]) {
		return $GLOBALS["memberList"][$userId];
	} else {
		$db = new DB();
		$db->query("SELECT Username from users WHERE id = '$userId' LIMIT 1;");
		list( $name ) = $db->fetchrow();
		$GLOBALS["memberList"][$userId] = $name;	
	}
	return $name;
}

function getNavBar() {
	$thisPageClass = " class=\"currentPage\"";
	
	if($_SESSION["userid"]) {
		$body .= "<a href=\"home.php\"" . ($_SERVER["PHP_SELF"] == "/TMS/home.php" ? $thisPageClass : "") . ">Home</a>";
		$body .= "<a href=\"time.php\"" . ($_SERVER["PHP_SELF"] == "/TMS/time.php" ? $thisPageClass : "") . ">Time</a>";
		if($_SESSION["isAdministrator"]) {
			$body .= "<a href=\"admin.php\"" . ($_SERVER["PHP_SELF"] == "/TMS/admin.php" ? $thisPageClass : "" ) . ">Admin</a>";
			
		}
		if($_SESSION["isProjectManager"]) {
			$body .= "<a href=\"manage-projects.php\"" . ($_SERVER["PHP_SELF"] == "/TMS/manage-projects.php" ? $thisPageClass : "" ) . ">Manage Projects</a>";
		}
		$body .= "<a href=\"reporting.php\"" . ($_SERVER["PHP_SELF"] == "/TMS/reporting.php" ? $thisPageClass : "") . ">Reporting</a>";
		
	}
	return "<div id=\"navbar\">$body</div>";
}


function authenticate() {
	if($_SESSION["username"]) {
		return true;
	} else {
		showContent( wrap("not-logged-in.html", array( "destination" =>  $_SERVER["PHP_SELF"] ) ) );
		exit;
	}
}


function movedPermanently( $newUrl ) {
	header ('HTTP/1.1 301 Moved Permanently');
  	forward($newUrl);
  	return true;
}
function forward( $newUrl) {
	header ("Location: " . $newUrl );
	return true;
}
function getMonthBreakdownSelect() {
	$start = strtotime("20080101 1200");


	$month = 0;
	$content = "<select name=\"month\">";
	while(mktime(0,0,0,date("m") - $month) > $start) {
		$date = date("Y-m",mktime(0,0,0,date("m") - $month));
		$dateName = date("F-Y", mktime(0,0,0,date("m") - $month++));
		$content .= "<option value=\"$date\">$dateName</option>\n";
		
	}
	$content .= "</select>";
	return $content;	
}



function showError($msg = "There was a problem with the database.") {
	showContent($msg);
	exit;
}

/* @TODO: Implement caching for multiple calls of this. */
function getUserList() {
	
	$db = new DB();
	
	// Create user list which is used by most user roles.
	$sql = "SELECT Username, id FROM tms_user ORDER BY Username";
	$db->query($sql);
	$userList = array();
	while( list( $user, $id ) = $db->fetchrow() ) {
		$userList[$user] = $id;
	}
	return $userList;
}

function timelogger() {
	require("spreadsheet.inc.php");
	
	// Handle form postbacks
	if($_POST["task"]) {
		updateSheet();
	}
	
	
	$db = new DB();
	$content = "<div class=\"standardBox\"><h1>Time Logging</h1><div>";
	$username  = $db->escape($_SESSION["username"]);
	$userid  = $_SESSION["userid"];
	
	$sql = "SELECT c.Name, p.project, t.task, t.id FROM tms_projectuser as pu " .
			"JOIN tms_task as t ON pu.projectId = t.projectId " . 
			"LEFT JOIN tms_project as p ON p.id = pu.projectId " . 
			"LEFT JOIN tms_client as c ON c.id = p.clientId " . 
			"WHERE pu.userid = '$userid' ORDER BY c.Name, p.project, t.Task";
	$db->query($sql);
	//$content .= $sql;
	if($db->size() == 0) {
		$content .= "You aren't currently assigned to any tasks, please ask a project manager to assign you to one.";
	} else {
		
		$previousClient = $previousProject = "";
		while(list($client, $project, $task, $tid) = $db->fetchrow()) {
			$data[$client][$project][$task] = $tid;
		}
		
		$content .= showSheet($data);
	}

	$content .= "</div></div>";
	return $content;
}

function reporting() {
	$submitButton = '<input type="submit" value="go"/>';

	$content = "<div class=\"standardBox\"><h1>Reporting</h1><div>\n";
	$content .= "User Month Hours:<br/>\n";
	$content .= "<form action=\"report.php\" method=\"post\"><input type=\"hidden\" name=\"type\" value=\"user-month\"/>" .
			"<select name=\"userid\">";
	foreach(getUserList() as $user=>$id) {
		$content .= "<option value=\"$id\">$user</option>\n";
	}
	$content .="</select>";
	
	// print list of months starting with this one backwards.
	$content .= getMonthBreakdownSelect();

	
	
	$content .= "$submitButton</form><br/><br/>";

	$content .= 'Adjust Actuals:<br/>' . 
			"\n<form action=\"report.php\" method=\"post\"><input type=\"hidden\" name=\"type\" value=\"adjust-actuals\"/>".
			getMonthBreakdownSelect() . $submitButton . "</form>";

	return $content;
}

function administration() {
	$content .=  "<div style=\"border: 1px solid;width:300px;\"><h1>User List</h1>";
	foreach(getUserList() as $user=>$id) {
		$content .=  "<div style=\"padding-left: 10px;\">" .
				"	<a href=\"edit-user.php?user=$id\">$user</a>" .
				"	[<a href=\"edit-user.php?action=delete&user=$id\" onclick=\"return confirmDelete('this user, ALL logged time, and project assignments');\">x</a>]" .	
				"</div>";
	}
	$content .=  "</div>\n";
	$content .=  "<a href=\"edit-user.php?action=new\">New User</a><br/><br/>\n\n<br/>\n";

	return  "<div class=\"standardBox\"><h1>Administration</h1><div>" . $content . "</div>\n</div>\n";
}

function projectManage() {
	$db = new DB();
	
	$content = "";
	$sql = "SELECT id, Name FROM tms_client ORDER BY Name";
	
	$content .= "<div style=\"border: 1px solid;width:300px;\"><h1>Client List</h1>";
	$db->query($sql);
	while( list( $id, $client ) = $db->fetchrow() ) {
		$content .=  "<div style=\"padding-left: 10px;\">" .
				"	<a href=\"edit-client.php?client=" . $id . "\">$client</a>" .
				"	[<a href=\"edit-client.php?action=delete&client=" . $id . "\">x</a>]" .	
				"</div>";
	}
	$content .=  "</div>\n";
	$content .=  "<a href=\"edit-client.php?action=new\">New Client</a><br/><br/>\n\n";
	
	
	return "<div class=\"standardBox\"><h1>Project Management</h1><div>" . $content . "</div></div>\n\n";
}

// Shows the dashboard
function getDashboard() {
	$db = new DB();
	// Get the list of projects, the total estimated hours, and the total spent hours.
	$db->query("SELECT p.project, c.name, 
				(SELECT SUM(t.ExpectedHours) FROM tms_task t WHERE t.projectId = p.id),
				(SELECT SUM(tl.hours) FROM tms_task t 
					JOIN tms_tasklogentry tl ON tl.taskId = t.id
					WHERE t.projectId = p.id),
				(SELECT MIN(tl.date) FROM tms_task t 
					JOIN tms_tasklogentry tl ON tl.taskId = t.id
					WHERE t.projectId = p.id),
				(SELECT MAX(tl.date) FROM tms_task t 
					JOIN tms_tasklogentry tl ON tl.taskId = t.id
					WHERE t.projectId = p.id)
			FROM tms_project p 
				JOIN tms_client c ON c.id = p.clientId 
			ORDER BY p.date DESC");
	while( list($project, $client, $total, $actual) = $db->fetchrow()) {
		if($total == "") {
			$data = "No tasks have been defined yet.";
		} else if ($actual == "") {
			$data = "No hours have been logged yet. Estimate: $total";
		} else {
			$data = "$actual / $total";
			
			$chartWidth = 500;
			if($actual > $total) {
				$overageWidth = ($actual - $total) / $total * $chartWidth;
				$overage = $actual - $total;
				$actual = $total;
				
			} else {
				$overageWidth = 0;
			}
		
			
			$style = "width: " . ($actual / $total) * $chartWidth . "px;background-color:green;";
			$result .= "\n<div style=\"width:{$chartWidth}px;border:3px solid cyan;position:relative;\"><div class=\"bar\" style=\"$style\">{$actual}h/{$total}h</div><div class=\"overage\" style=\"width: {$overageWidth}px;background-color:red;position:absolute;right:-{$overageWidth}px;top:0px;\">{$overage}h</div></div>";
		}
		$result .=  "$client - $project<hr/>";

	}
	$result .= "</table>";
	
	return $result;
	
	

}
	