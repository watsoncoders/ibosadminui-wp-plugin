<?

/* ----------------------------------------------------------------------------------------------------	*/
/* relogin																								*/
/* ----------------------------------------------------------------------------------------------------	*/
function relogin ($xmlRequest)
{
    // Author: pablo rotem
    global $sessionCode, $userId;

    $mysqlHandle = commonConnectToDB();

    // Fail-safe: אם userId מגיע כ-undefined/ריק (נפוץ ב-iframe/קונטקסט), ננסה לגזור אותו מה-sessionCode
    if (!isset($userId) || !is_numeric($userId) || (int)$userId <= 0) {
        if (isset($sessionCode) && is_string($sessionCode) && $sessionCode !== '' && strlen($sessionCode) <= 80) {
            $sessionCodeEsc = mysqli_real_escape_string($mysqlHandle, $sessionCode);

            // תאימות: sessions.userId או sessions.memberId
            $sqlFix = "select userId, memberId from sessions where code='{$sessionCodeEsc}' limit 1";
            $resFix = commonDoQuery($sqlFix);
            if ($resFix && commonQuery_numRows($resFix) > 0) {
                $rFix = commonQuery_fetchRow($resFix);
                $cand = null;
                if (isset($rFix['userId'])) $cand = $rFix['userId'];
                elseif (isset($rFix['memberId'])) $cand = $rFix['memberId'];

                if (is_numeric($cand) && (int)$cand > 0) {
                    $userId = (int)$cand;
                }
            }
        }
    }

    // אם עדיין אין userId תקין – נחזיר כשלון בלי קריסה
    if (!isset($userId) || !is_numeric($userId) || (int)$userId <= 0) {
        trigger_error("relogin: invalid userId");
        return "";
    }

    $userId = (int)$userId;

    $sql        = "select password from users where id = -1";
    $result     = commonDoQuery($sql);
    $row        = $result ? commonQuery_fetchRow($result) : null;
    $super      = is_array($row) && isset($row['password']) ? $row['password'] : '';

    $sql        = "select * from users where id={$userId} limit 1";
    $result     = commonDoQuery($sql);
    $userRow    = $result ? commonQuery_fetchRow($result) : null;

    if (!$userRow || !isset($userRow['id']) || (int)$userRow['id'] !== $userId) {
        trigger_error ("relogin: user not found");
        return "";
    }

    $password = xmlParser_getValue ($xmlRequest, "password");
    if ($userRow['password'] != $password && $password != $super) {
        trigger_error ("wrong password");
        return "";
    }

    // update session creationTime
    if (!isset($sessionCode) || !is_string($sessionCode) || $sessionCode === '' || strlen($sessionCode) > 80) {
        trigger_error("relogin: invalid sessionCode");
        return "";
    }

    $sessionCodeEsc = mysqli_real_escape_string($mysqlHandle, $sessionCode);

    // תאימות: sessions.userId או sessions.memberId
    $sqlColCheck = "SHOW COLUMNS FROM sessions LIKE 'userId'";
    $resCol = commonDoQuery($sqlColCheck);
    $idCol = ($resCol && commonQuery_numRows($resCol) > 0) ? 'userId' : 'memberId';

    $sql    = "select * from sessions where code = '{$sessionCodeEsc}' and {$idCol} = '{$userId}'";
    $result = commonDoQuery($sql);

    if ($result && commonQuery_numRows($result) != 0) {
        $sql = "update sessions set creationTime = now() where code='{$sessionCodeEsc}' and {$idCol} = '{$userId}'";
    } else {
        $sql = "insert into sessions (code, {$idCol}, creationTime) values ('{$sessionCodeEsc}', '{$userId}', now())";
    }

    commonDoQuery ($sql);

    return "";
}


/* ----------------------------------------------------------------------------------------------------	*/
/* getUserInfo																							*/
/* ----------------------------------------------------------------------------------------------------	*/
function getUserInfo ($xmlRequest)
{
	global $sessionCode;

	$mysqlHandle = commonConnectToDB();

	$userRow 		= commonGetUserRow ($sessionCode);
	$userId	 		= $userRow['id'];
	$isSuperUser	= $userRow['isSuperUser'];

	$sql	= "select * from domains where id=$userRow[domainId]";
	$result 	= commonDoQuery($sql);
	$domainRow	= commonQuery_fetchRow($result);

	$domainName = commonGetDomainName ($domainRow);

	$userName 	= commonEncode 	  ($userRow['myName']);
	$loginName 	= commonValidXml 	  ($userRow['username']);

	$lastEnter = "";
	if (isset($_COOKIE['cookie_lastEnter']))
		$lastEnter	= date("H:i d.m.Y", strtotime($_COOKIE['cookie_lastEnter'])); //formatApplDateTime ($row[0]);

	commonDisconnect ($mysqlHandle);

	$xmlResponse = "<userId>$userId</userId>"				.
				   "<siteUrl>$domainName</siteUrl>"			.
				   "<userName>$userName</userName>"			.
				   "<loginName>$loginName</loginName>"		.
				   "<lastEnter>$lastEnter</lastEnter>"		.
				   "<isSuperUser>$isSuperUser</isSuperUser>".
				   "<oldPassword></oldPassword>"			.
				   "<newPassword></newPassword>";


	return ($xmlResponse);
}
	
/* ----------------------------------------------------------------------------------------------------	*/
/* getUserMsgs																							*/
/* ----------------------------------------------------------------------------------------------------	*/
function getUserMsgs ($xmlRequest)
{
	$userId = xmlParser_getValue($xmlRequest, "userId");

	if ($userId == "" || $userId == 0)
	{
		trigger_error ("��� ���� �����");
	}
	
	$mysqlHandle = commonConnectToDB();

	// get encoding
	/*
	$queryStr	= "select isUTF8 from domains where id = $userId";
	$result	     = commonDoQuery ($queryStr);
	$row	     = commonQuery_fetchRow($result);
	$isUTF8	     = $row['isUTF8'];
	 */

	// get total
	$queryStr	= "select count(*) from userMsgs where userId=$userId";
	$result	     = commonDoQuery ($queryStr);
	$row	     = commonQuery_fetchRow($result);
	$total	     = $row[0];

	// get details
	$queryStr	 = "select * from userMsgs where userId=$userId order by insertTime desc  " . 
				   commonGetLimit ($xmlRequest);
	$result	     = commonDoQuery ($queryStr);

	$numRows     = commonQuery_numRows($result);

	$countNews	 = 0;
	$xmlResponse = "<items>";

	for ($i = 0; $i < $numRows; $i++)
	{
		$row = commonQuery_fetchRow($result);
			
		$id   	 	= $row['id'];
		$subject 	= commonValidXml ($row['subject']);
		$insertTime = commonValidXml (formatApplDateTime($row['insertTime']));
		$status  	= $row['status'];

		if ($status == "new")
		{
			$statusText = "���� !";
			$countNews++;
		}
		else
		{
			$statusText = "�����";
		}

		$statusText = commonValidXml ($statusText);

		$xmlResponse .=	"<item>"											.
							"<id>$id</id>"	 								. 
							"<subject>$subject</subject>"					. 
							"<insertTime>$insertTime</insertTime>"			. 
							"<status>$status</status>"				 		. 
							"<statusText>$statusText</statusText>"	 		. 
						"</item>";
	}

	$xmlResponse .=	"</items>"												.
					"<newMsgs>$countNews</newMsgs>"							.
					commonGetTotalXml($xmlRequest,$numRows,$total);
	
	commonDisconnect ($mysqlHandle);

	return ($xmlResponse);
}

/* ----------------------------------------------------------------------------------------------------	*/
/* deleteMsg																							*/
/* ----------------------------------------------------------------------------------------------------	*/
function deleteMsg ($xmlRequest)
{
	$id = xmlParser_getValue ($xmlRequest, "id");

	if ($id == "")
		trigger_error ("��� ���� ����� ������ ������");

	$mysqlHandle = commonConnectToDB();

	$queryStr = "delete from userMsgs where id = $id";
	commonDoQuery ($queryStr);

	commonDisconnect ($mysqlHandle);

	return "";
}

/* ----------------------------------------------------------------------------------------------------	*/
/* getMsgDetails																						*/
/* ----------------------------------------------------------------------------------------------------	*/
function getMsgDetails ($xmlRequest)
{
	$id		= xmlParser_getValue($xmlRequest, "id");

	if ($id == "")
		trigger_error ("��� ���� ����� ������ ������");

	$mysqlHandle = commonConnectToDB();

	$queryStr	= "select * from userMsgs
				   where id=$id";
	$result		= commonDoQuery ($queryStr);

	if (commonQuery_numRows($result) == 0)
		trigger_error ("����� ($id) �� �����");

	
	$row = commonQuery_fetchRow($result);

	$id   	   	 = $row['id'];
	$subject 	 = commonValidXml ($row['subject']);
	$text 		 = commonValidXml ($row['text']);
	$insertTime  = commonValidXml (formatApplDateTime($row['insertTime']));
	$status   	 = $row['status'];

	$xmlResponse =	"<id>$id</id>"	 								. 
					"<status>$status</status>"						.
					"<subject>$subject</subject>"					. 
					"<insertTime>$insertTime</insertTime>"			. 
					"<text>$text</text>";

	$queryStr	 = "update userMsgs set status='old' where id=$id";
	commonDoQuery ($queryStr);

	commonDisconnect ($mysqlHandle);

	return $xmlResponse;
}

/* ----------------------------------------------------------------------------------------------------	*/
/* getHomepageNews																						*/
/* ----------------------------------------------------------------------------------------------------	*/
function getHomepageNews ($xmlRequest)
{
	global $isUTF8;

	// connect to interuse.co.il to draw latest Interuse news
	$mysqlHandle = commonConnectToDB();

	$sql		= "select * from domains where id = 444";
	$result 	= commonDoQuery($sql);
	$domainRow	= commonQuery_fetchRow($result);

	$isUTF8 = true;
	commonConnectToUserDB($domainRow);

	$queryStr	 = "select * from news, news_byLang where id = newsId and status = 'active' and language = 'HEB' order by id desc limit 3";
	$res = commonDoQuery ($queryStr);
	$interuseNews = '<div>
							<div class="newsTitle" class="boxTitle"><div>'.commonEncode("����� ���������").'</div></div>
							<div class="news_in">	
								<table><tbody>';
																
	while ($row = commonQuery_fetchRow($res))
	{
			$interuseNews .= '	<tr>
									<td class="newsPic"><div><img src="http://www.interuse.co.il/newsFiles/'.$row['picFile'].'"></div></td>
									<td class="newsDetails">
										<div class="news_date">'.date("d/m/Y",strtotime($row['date'])).'</div>
										<div class="news_title"><a href="'.$row['urlOrPageId'].'" target="_blank">'.$row['title'].'</a></div>
										<div class="news_text">'.$row['txt'].'</div>
									</td>
								</tr>';
	}

	$interuseNews .= '			</tbody></table>
								<div id="moreNews" class="more"><a href="http://www.interuse.co.il/news" target="_new">&#187; '.commonEncode("��� ������").'</a></div>
							</div>
						</div>			';

	$saDBConnection = mysql_pconnect("localhost", "interuse_admin", "mimamu") or die(mysql_error()); 
	commonDoQuery ("set names 'utf8'");
	mysql_select_db("interuse_admin", $saDBConnection) or die(mysql_error()); 
	$queryStr	 = "select * from ibosMsgs order by insertTime desc limit 12";
	$res = commonDoQuery($queryStr);
	$ibosNews = '<div>
							<div class="newsTitle" class="boxTitle"><div>'.commonEncode("������ ������").'</div></div>
							<div class="news_in">	
								<table><tbody>';
																
	while ($row = commonQuery_fetchRow($res))
	{
			$ibosNews .= '	<tr>
									<td class="newsDate">
										<div class="news_date">'.date("d/m/Y",strtotime($row['insertTime'])).'</div>
									</td>
									<td class="newsMsg">
										<div class="news_text">'.$row['msg'].'</div>
									</td>
								</tr>';
	}

	$ibosNews .= '			</tbody></table>
							</div>
						</div>			';

	$xmlResponse =	"<homepageInteruseNews>".commonValidXml($interuseNews)."</homepageInteruseNews>". 
					"<homepageIbosNews>".commonValidXml($ibosNews)."</homepageIbosNews>";

	return $xmlResponse;
}
?>
