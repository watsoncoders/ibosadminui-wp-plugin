<?php
include "commonAdmin.php";

/**
 * התאמות מצב "ללא Sessions" עבור תוסף וורדפרס.
 * Author: pablo rotem
 */
if (function_exists('is_user_logged_in') && !is_user_logged_in()) {
    if (function_exists('auth_redirect')) {
        auth_redirect();
        exit;
    }
    die('יש להתחבר למערכת וורדפרס כדי לגשת למסך זה.');
}

$currentWpUserId = function_exists('get_current_user_id') ? (int)get_current_user_id() : 0;
$currentWpUser   = function_exists('wp_get_current_user') ? wp_get_current_user() : null;

// Security (legacy session code אינו נדרש במצב תוסף ללא Sessions)
$sessionCode = isset($_REQUEST['sessionCode']) ? (string)$_REQUEST['sessionCode'] : '';
list($websiteName, $pluginResults, $websiteLink) = commonValidateSession (true);

if ($websiteName)
{
	$sql		= "select langs from globalParms";
	$result		= commonDoQuery ($sql);
	$row		= commonQuery_fetchRow($result);

	$usedLangs	= $row['langs'];

	setcookie ("goToPage","");
}
else
{
    // במצב תוסף, אם אין "websiteName" נחזיר שם אתר כללי ולא נזרוק החוצה.
    $websiteName = function_exists('get_bloginfo') ? get_bloginfo('name') : 'iBOS';
    $websiteLink = function_exists('home_url') ? home_url('/') : '#';
    $pluginResults = '';
}

$guiLang = isset($_COOKIE['cookie_guiLang']) ? $_COOKIE['cookie_guiLang'] : '';

if ($guiLang == "HEB" || $guiLang == "")
{
	$guiLang   = "HEB";

	$accountText   	= "מצב חשבון";
	$supportText  	= "קריאת שרות";
	$exitText  		= "יציאה";
}
else
{
	$accountText   	= "My account";
	$supportText  	= "Support";
	$exitText  		= "Exit";
}

$tables = array();
$sql	= "show table status";
$result	= commonDoQuery($sql);

while ($row = commonQuery_fetchRow($result))
{
	if ($row['Rows'] != 0)
		array_push ($tables, $row['Name']);
}

// connect to interuse db
$conn = commonConnectToDB();

commonDoQuery("set names 'utf8'", $conn);

// משתמש נוכחי (וורדפרס). במידה ואין מיפוי לטבלת users של iBOS, נשתמש בפרטי WP.
$userRow = commonGetUserRow($sessionCode);
if (!is_array($userRow) || empty($userRow['id'])) {
    $userRow = array(
        'id' => $currentWpUserId,
        'domainId' => 0,
        'myName' => ($currentWpUser && isset($currentWpUser->display_name)) ? $currentWpUser->display_name : 'Admin',
        'prevEnter' => ''
    );
}
$userId = (int)$userRow['id'];
$domainIdUser = isset($userRow['domainId']) ? (int)$userRow['domainId'] : 0;


// get isSuper (ללא Sessions -> סופר אדמין קבוע)
$isSuper = 1;
commonDoQuery("set names 'latin1'", $conn);

$sql = "select * from users where id = " . (int)$userId;
$result = commonDoQuery($sql);
$tmpUserRow = $result ? commonQuery_fetchRow($result) : null;
if (is_array($tmpUserRow) && !empty($tmpUserRow)) {
    $userRow = $tmpUserRow;
    $domainIdUser = isset($userRow['domainId']) ? (int)$userRow['domainId'] : $domainIdUser;
}
commonDoQuery("set names 'utf8'", $conn);

//$username	= ""; //iconv("windows-1255", "utf-8", stripslashes($userRow['myName']));
$rawName = isset($userRow['myName']) ? (string)$userRow['myName'] : '';
if ($rawName === '' && $currentWpUser && isset($currentWpUser->display_name)) {
    $rawName = (string)$currentWpUser->display_name;
}
$username = $rawName !== '' ? @iconv("windows-1255", "utf-8", stripslashes($rawName)) : 'Admin';
if ($username === false || $username === null || $username === '') {
    $username = $rawName !== '' ? stripslashes($rawName) : 'Admin';
}
$menu 		= "";
$subMenus 	= "";
$prevGroup 	= "";
$groupId	= 0;
$floatMenu	= "";

// Add the first menu - Recently Used
$sql = "select features_utf8.*, featuresGroups_utf8.subDirectory as groupName, 
			   features_utf8.description" . ($guiLang == "HEB" ? "" : "_ENG") . " as featureText
		from (usedFeatures, features_utf8)
		left join featuresGroups_utf8 on featuresGroups_utf8.id = features_utf8.groupId
		where features_utf8.id = usedFeatures.featureId and userId = $userId and isSuper = $isSuper
	   	order by usedFeatures.lastUsedAt desc limit 10";
$result = commonDoQuery($sql);
if ($result)
{
	$menuTitle = ($guiLang == "HEB" ?  "בשימוש לאחרונה" : "Recently Used");

	$menu 	   = "<a href='javascript:void(0)' id='mb$groupId' class='easyui-menubutton' data-options=\"menu:'#mm$groupId' \">$menuTitle</a>";

	$floatMenu	= "<div id='floatMenuTitle'><div>$menuTitle</div></div>";

	$subMenus .= "<div id='mm$groupId' style='width:160px;'>";
	while ($row = commonQuery_fetchRow($result))
	{
			if ($row['domainId'] == "0")
				$htmlFile = "../html/$row[groupName]/$row[handleFile].html";
			else
				$htmlFile = "../html_plugins/$row[domainId]/$row[handleFile].html";

			$htmlFile .= "?t=" . filemtime($htmlFile);

//			$text		= iconv("windows-1255", "utf-8", stripslashes($row['featureText']));
			$text		= stripslashes($row['featureText']);
			$click		= "generalShowPage($row[id], \"$htmlFile\")";

			$subMenus .= "<div onclick='$click' data-options=\"iconCls:'icon-active'\">$text</div>";

			$floatMenu	.= "<div class='floatMenu_link' onclick='$click'><div>$text</div></div>";
	}
	$subMenus .= "</div>";

	$floatMenu .= "<div class='floatMenu_link green' onclick='location.href=\"../../index.php\"'><div>$exitText</div></div>";

	$groupId++;
}

// get user features
$sql = "select features_utf8.*, featuresGroups_utf8.subDirectory as groupName, 
			   features_utf8.description" . ($guiLang == "HEB" ? "" : "_ENG") . " as featureText,
			   featuresGroups_utf8.description" . ($guiLang == "HEB" ? "" : "_ENG") . " as groupText, usersFeatures.handleFile as userHandleFile
		from featuresGroups_utf8, features_utf8, usersFeatures
		where featuresGroups_utf8.id = features_utf8.groupId
		and   features_utf8.id = usersFeatures.featureId
		and   usersFeatures.userId = $userId
		and   showInMenu = 1
		order by featuresGroups_utf8.pos, features_utf8.pos";
		
$result = commonDoQuery($sql);

while ($row = commonQuery_fetchRow($result))
{
	$featureId	= $row['id'];
	$handleFile = $row['handleFile'];

	if ($row['userHandleFile'] != "")
		$handleFile = $row['userHandleFile'];

	$name		= str_replace("handle", "", $handleFile);
//	$text		= iconv("windows-1255", "utf-8", stripslashes($row['featureText']));
	$text		= stripslashes($row['featureText']);
	$byLang		= $row['byLang'];
	$domainId	= $row['domainId'];
	$currGroup	= $row['groupId'];
	$groupName	= $row['groupName'];
//	$groupText	= iconv("windows-1255", "utf-8", $row['groupText']);
	$groupText	= $row['groupText'];

	if (in_array($row['mainDbTable'], $tables) || $row['mainDbTable'] == "")
		$active = "data-options=\"iconCls:'icon-active'\"";
	else
		$active = "";
		
	if (($prevGroup != $currGroup) || ($prevGroup != $currGroup && $currGroup != 8))
	{
		$groupId++;

		$prevGroup = $currGroup;

		$menu .= "<a href='javascript:void(0)' id='mb$groupId' class='easyui-menubutton' data-options=\"menu:'#mm$groupId' \">$groupText</a>";
		if ($subMenus)
			$subMenus .= "</div>";
		
		$width		= (($groupId == 11 && ($userRow['domainId'] == 346 || $userRow['domainId'] == 540)) ? "170" : "150");
		$subMenus .= "<div id='mm$groupId' style='width:${width}px;'>";
	}

	if ($domainId == "0")
	{
		$htmlFile = "../html/$groupName/$handleFile.html";
	}
	else
	{
		$htmlFile = "../html_plugins/$domainId/$handleFile.html";
	}

	$htmlFile .= "?t=" . filemtime($htmlFile);

	$subMenus .= "<div onclick='generalShowPage($featureId, \"$htmlFile\")' $active>$text</div>";
}
$subMenus .= "</div>";

$hello = "<table>
		  <tr>
		  		<td class='font13'>&nbsp;&nbsp;&nbsp;" . ($guiLang == "ENG" ? "Hello" : "שלום") . " $username</td>";

if (isset($userRow['prevEnter']) && $userRow['prevEnter'] != "")
{
	$hello .= "<td class='font13'>,&nbsp;" . (($guiLang == "ENG") ? "Your latest login was at " : "מועד כניסתך האחרון") . "</td>
			   <td>&nbsp;<span class='sep'></span>&nbsp;</td>
			   <td class='font11'>
			   	<div style='padding-top:2px;'>&nbsp; " . date("d.m.Y , H:i", strtotime($userRow['prevEnter'])). "&nbsp;&nbsp;</div>
			   </td>";
}

$hello .= "</tr>
		   </table>";

$websiteText = "<div class='title'>" . ($guiLang == "ENG" ? "You're managing" : "האתר המנוהל") . "<span class='sep'></span></div>
				<div><span id='siteNameBox'><a href='$websiteLink' target='_new'>$websiteName</a></span></div>";

commonDisconnect ($conn);

commonConnectToDB ();

$gotoPage 		= "../html/general/userHomePage.html";
$supportText	= "";
$accountText	= "";

if ($domainIdUser == 530 && $userId != 3135)	// loox
{
	$gotoPage 		= "../html_plugins/530/handleLooxOrders.html";
	$supportText	= "";
	$accountText	= "";
	$menu			= "";
	$subMenus		= "";
	$floatMenu		= "";
}


?>

<!DOCTYPE html>
<html dir="rtl">
	<head>
		<meta http-equiv="content-type" content="text/html;charset=utf-8">
		<title>i-bos - מערכת ניהול אתרים דינמיים</title>
		<link rel="stylesheet" href="../css/common.css" type="text/css">
		<link rel="stylesheet" href="../css/<?php echo $guiLang; ?>.css" type="text/css">
		<link rel="stylesheet" href="../easyui/easyui.css" type="text/css">
		<?php
			if ($guiLang == "HEB") 
				echo "<link rel='stylesheet' type='text/css' href='../easyui/easyui-rtl.css'>\n";

			$privateCss = "";
			if (file_exists("../css/private".$domainIdUser.".css"))
			{
				echo "<link rel='stylesheet' type='text/css' href='../css/private".$domainIdUser.".css'>\n";
				$privateCss = "private".$domainIdUser.".css";
			}

		?>
		<script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/1.8.2/jquery.min.js"></script>
		<script type="text/javascript" src="../easyui/jquery.easyui.min.js"></script>
		<?php
			if ($guiLang == "HEB") 
				echo "<script type='text/javascript' src='../easyui/easyui-rtl.js'></script>\n";
		?>
		<script type="text/javascript" src="../javascript2/xmlObj.js"></script>
		<script type="text/javascript" src="../javascript2/serverInterfaceObj.js"></script>
		<script type="text/javascript" src="../javascript2/common.js"></script>
		<script type="text/javascript" src="../javascript2/general.js"></script>
		<script type="text/javascript">
		<!--
				$(document).ready(function() 
				{
					if ($(window).width() >= 1200)
					{
						$("#mb0").hide();
						$("#floatMenu").show();
					}
					else
					{
						$("#mb0").show();
						$("#floatMenu").hide();
					}

					var langs = "<?php echo $usedLangs; ?>";

					commonSetGlobalData ("featureId", "0");

					commonSetGlobalData ("langArray", 		 langs.split(","));
					commonSetGlobalData ("guiLang", 		 "<?php echo $guiLang; ?>");
					commonSetGlobalData ("websiteShortName", "<?php echo $websiteName; ?>");
					commonSetGlobalData ("sessionCode", 	 "<?php echo $sessionCode; ?>");
					commonSetGlobalData ("userId", 			 "<?php echo $userId; ?>");
					commonSetGlobalData ("siteUrl",			 "<?php echo $websiteLink; ?>");
					commonSetGlobalData ("privateCss",		 "<?php echo $privateCss; ?>");
				});
					
				$(window).resize(function() 
				{
					if ($(window).width() >= 1200)
					{
						$("#mb0").hide();
						$("#floatMenu").show();
					}
					else
					{
						$("#mb0").show();
						$("#floatMenu").hide();
					}
				});
		-->		
		</script>
	</head>
	<body>
		<div id="header">
			<div id="header_in">
				<div id="logo"><img src="../designFiles/ibos.png" title="i-Bos גרסה 3.0"
					style="cursor:pointer" onclick="generalShowPage(1, '<?php echo $gotoPage; ?>')" /></div>
				<div id="headerTop">
					<table>
					<tr>
						<td id="headerTop_row1_col1">
							<div class="headerTop_col_in">
								&nbsp;&nbsp;
								<a href="http://www.interuse.com/hesk22/index.php?a=add" target="_blank"><?php echo $supportText; ?></a>
								&nbsp;&nbsp;&nbsp;
								<a href="http://www.interuse.com/?user=<?php echo $userId; ?>" target="_blank"><?php echo $accountText; ?></a>
								&nbsp;&nbsp;
								<a href="../../index.php"><img src="../designFiles/iconExit.png" />&nbsp; <?php echo $exitText; ?></a>
								&nbsp;&nbsp;&nbsp;
							</div>
						</td>
						<td id="headerTop_vsep" rowspan="3"><div></div></td>
						<td id="headerTop_col2" rowspan="3">
							<div class="headerTop_col_in"><?php echo $websiteText; ?></div>
						</td>
					</tr>
					<tr>
						<td id="headerTop_row2_col1">
							<div class="headerTop_col_in font13"><?php echo $hello; ?></div>
						</td>
					</tr>
					</table>
				</div>
			</div>
		</div>
		<div id="mainMenu">
			<div id="mainMenu_in">
				<?php echo $menu; ?>
				<?php echo $subMenus; ?>
			</div>
		</div>
		<div id="mainHtml">
			<iframe name ="mainFrame" frameborder="0" width="980" height="900" style="margin:0px" src="<?php echo $gotoPage; ?>"></iframe>
		</div>
		<div id="floatMenu"><?php echo $floatMenu; ?></div>
		<div id="globalData"></div>
	</body>
</html>
