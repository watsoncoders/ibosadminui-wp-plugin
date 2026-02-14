<?php
/**
 * server.php
 * Fixed for PHP 8.3
 * author: pablo rotem
 */

date_default_timezone_set("Asia/Jerusalem");

// simulate $HTTP_RAW_POST_DATA in newer PHP
if (PHP_VERSION_ID >= 70000) {
    $HTTP_RAW_POST_DATA = file_get_contents("php://input");
}

require_once "commonAdmin.php";
require_once "xmlParser.php";
require_once "format.php";

/* ----------------------------------------------------------
   errorHandler
   ---------------------------------------------------------- */
function errorHandler($errno, $errstr, $errfile, $errline)
{
    global $HTTP_RAW_POST_DATA, $sessionCode;

    if ($errstr != "") {
        $errstr = iconv("windows-1255", "utf-8", $errstr);
    }

    echo "<?xml version='1.0' encoding='UTF-8' ?>" .
         "<interuse>" .
            "<response>" .
                "<responseType>Error</responseType>" .
                "<message>$errstr</message>" .
                "<file>$errfile</file>" .
                "<line>$errline</line>" .
            "</response>" .
         "</interuse>";

    $mysqlHandle = commonConnectToDB();
    $userRow     = commonGetUserRow($sessionCode);
    $domainId    = $userRow['domainId'] ?? 'unknown';

    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // platform
    $platform = 'unknown';
    if (preg_match('/linux/i', $userAgent)) {
        $platform = 'linux';
    } elseif (preg_match('/macintosh|mac os x/i', $userAgent)) {
        $platform = 'mac';
    } elseif (preg_match('/windows|win32/i', $userAgent)) {
        $platform = 'windows';
    }

    $bname = 'Unknown';
    $ub    = 'other';

    if (preg_match('/MSIE/i', $userAgent) && !preg_match('/Opera/i', $userAgent)) {
        $bname = 'Internet Explorer';
        $ub    = 'MSIE';
    } elseif (preg_match('/Firefox/i', $userAgent)) {
        $bname = 'Mozilla Firefox';
        $ub    = 'Firefox';
    } elseif (preg_match('/Chrome/i', $userAgent)) {
        $bname = 'Google Chrome';
        $ub    = 'Chrome';
    } elseif (preg_match('/Safari/i', $userAgent)) {
        $bname = 'Apple Safari';
        $ub    = 'Safari';
    } elseif (preg_match('/Opera/i', $userAgent)) {
        $bname = 'Opera';
        $ub    = 'Opera';
    } elseif (preg_match('/Netscape/i', $userAgent)) {
        $bname = 'Netscape';
        $ub    = 'Netscape';
    }

    // browser version
    $known   = array('Version', $ub, 'other');
    $pattern = '#(?<browser>' . join('|', $known) . ')[/ ]+(?<version>[0-9.|a-zA-Z.]*)#';

    $matches = array();
    if (!preg_match_all($pattern, $userAgent, $matches)) {
        // no matching number - continue
    }

    $version = '?';
    $i       = isset($matches['browser']) ? count($matches['browser']) : 0;

    if ($i != 1) {
        if (strpos($userAgent, "Version") !== false &&
            strpos($userAgent, "Version") < strpos($userAgent, $ub)
        ) {
            $version = $matches['version'][0] ?? '?';
        } else {
            $version = $matches['version'][1] ?? '?';
        }
    } else {
        $version = $matches['version'][0] ?? '?';
    }

    if ($version === null || $version === "") {
        $version = "?";
    }

    $userId   = $userRow['id'] ?? 'unknown';
    $username = $userRow['username'] ?? 'unknown';

    if (strpos($errstr, "Undefined offset") === false) {
        @mail(
            "liat@interuse.com",
            "I-Bos ERROR",
            "Session code = $sessionCode\n" .
            "Domain = $domainId\n" .
            "User = $userId - $username\n" .
            "Error Message: $errstr\n" .
            "File: $errfile\n" .
            "Line: $errline\n" .
            "Browser: $bname $version $platform\n" .
            "Xml:\n$HTTP_RAW_POST_DATA\n" .
            "Server: " . var_export($_SERVER, true)
        );
    }

    exit;
}

function trigger_debug($debugStr)
{
    if ($debugStr != "") {
        $debugStr = iconv("windows-1255", "utf-8", $debugStr);
    }

    echo "<?xml version='1.0' encoding='UTF-8' ?>" .
         "<interuse>" .
            "<response>" .
                "<responseType>Debug</responseType>" .
                "<message>$debugStr</message>" .
            "</response>" .
         "</interuse>";
    exit;
}

set_error_handler("errorHandler", E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED & ~E_WARNING);

if (strpos($HTTP_RAW_POST_DATA, "pleaseWaitMsg") !== false ||
    strpos($HTTP_RAW_POST_DATA, "  ") !== false
) {
    trigger_error("!   .   ");
}

$xmlRequest = xmlParser_parse($HTTP_RAW_POST_DATA);

$sessionCode = xmlParser_getValue($xmlRequest, "sessionCode");
$userId      = xmlParser_getValue($xmlRequest, "userId");
$usedLangs   = xmlParser_getValue($xmlRequest, "usedLangs");
$command     = xmlParser_getValue($xmlRequest, "command");
$requestId   = xmlParser_getValue($xmlRequest, "requestId");

$splitCommand = explode(".", $command);

// first session validation
if ($splitCommand[1] != "relogin" &&
    $splitCommand[1] != "saveChoice" &&
    !commonValidateSession()
) {
    echo "<interuse>
             <response>
                 <responseType>SessionExpired</responseType>
                 <message></message>
                 <file></file>
                 <line></line>
             </response>
          </interuse>";
    exit;
}

$cookie_guiLang = $_COOKIE['cookie_guiLang'] ?? 'HEB';

// get function name & server file
$functionName = $splitCommand[1];
$fileName     = "commands_" . $splitCommand[0] . ".php";

// get domainId
$mysqlHandle = commonConnectToDB();
$userRow     = commonGetUserRow($sessionCode);
$domainId    = $userRow['domainId'] ?? 0;

if (strpos($HTTP_RAW_POST_DATA, "</interuse>") === false) {
    @mail(
        "liat@interuse.com",
        "I-Bos - incomplete request",
        "Session code = $sessionCode\n" .
        "Domain = $domainId\n" .
        "User = " . ($userRow['id'] ?? 'unknown') . " - " . ($userRow['username'] ?? 'unknown') . "\n" .
        "Xml:\n$HTTP_RAW_POST_DATA"
    );

    trigger_error("incomplete XML request");
}

// feature / permission check
if ($fileName != "commands_user.php" &&
    $functionName != "getSiteLangs" &&
    $functionName != "getAllPages" &&
    $functionName != "getStyles" &&
    $functionName != "getSiteNames" &&
    $functionName != "getSiteName" &&
    $fileName != "commands_flags.php" &&
    $fileName != "commands_general.php" &&
    $fileName != "commands_enums.php"
) {
    $featureDomainId = "0";

    $sql    = "select id, domainId from features_utf8 where concat(commandsFile,'.php') = '$fileName' and (domainId = 0 or domainId = '$domainId')";
    $result = commonDoQuery($sql);
    if (commonQuery_numRows($result) != 0) {
        $featureRow      = commonQuery_fetchRow($result);
        $featureDomainId = $featureRow['domainId'];
    } else {
        trigger_error("feature not found for domain/file ($domainId - $fileName)");
    }

    $usersFeaturesTable = "usersFeatures";

    $sql    = "select count(*) from $usersFeaturesTable where userId = $userId and featureId = " . $featureRow['id'];
    $result = commonDoQuery($sql);
    $row    = commonQuery_fetchRow($result);
    if ($row[0] == 0) {
        trigger_error("user has no permission for feature ($featureRow[id] - $fileName - $command).");
    }
}

// duplicate request check
if (strpos($functionName, "get") === false &&
    strpos($functionName, "update") === false &&
    strpos($functionName, "excelReport") === false &&
    strpos($functionName, "preview") === false
) {
    $queryStr = "select * from requestsLog where requestId = '$requestId' and requestId != ''";
    $result   = commonDoQuery($queryStr);
    if (commonQuery_numRows($result) != 0) {
        @mail(
            "liat@interuse.com",
            "I-Bos Duplicate",
            "Session code = $sessionCode\n" .
            "Domain = $domainId\n" .
            "User = " . ($userRow['id'] ?? 'unknown') . " - " . ($userRow['username'] ?? 'unknown') . "\n" .
            "Function Name: $functionName\n" .
            "Xml:\n$HTTP_RAW_POST_DATA"
        );

        echo "<interuse>
                 <response>
                     <responseType>Duplicate</responseType>
                     <command>$command</command>
                     <requestId>$requestId</requestId>
                     <responseData>
                     </responseData>
                 </response>
              </interuse>";
        exit;
    }
}

// delete old requests
$before   = date("Y-m-d H:i:00", strtotime("-1 hours"));
$queryStr = "delete from requestsLog where datetime < '$before'";
commonDoQuery($queryStr);

// add new request
$queryStr = "insert into requestsLog (requestId, datetime) values ('$requestId', now())";
commonDoQuery($queryStr);

// sanity check
if (count($splitCommand) != 2) {
    trigger_error("Wrong command name format (missing server file name) - $command");
}

// second session validation
if ($splitCommand[1] != "relogin" &&
    $splitCommand[1] != "saveChoice" &&
    !commonValidateSession()
) {
    echo "<interuse>
             <response>
                 <responseType>SessionExpired</responseType>
                 <message></message>
                 <file></file>
                 <line></line>
             </response>
          </interuse>";
    exit;
}

// plugin path logic
if (!file_exists($fileName)) {
    if ($featureDomainId == "0") {
        trigger_error("plugin featureDomainId is 0");
    }

    $fileName = "plugins/" . abs($featureDomainId) . "/$fileName";
}

if ($functionName == "getUserMsgs" || $functionName == "getMsgDetails") {
    global $isUTF8;
    $isUTF8 = 0;
}

/* // security: prevent LFI
$baseFileName = basename($fileName);
if ($baseFileName != $fileName) {
    trigger_error("Invalid command format (path traversal detected) - $command");
    exit;
}*/
// --- Security Fix Start --- // Fixed by pablo Rotem for latest php 14.11.2025
// בדיקת אבטחה בסיסית למניעת LFI / Path Traversal
// מוודאים שאין ../ ואין נתיב מוחלט שמתחיל ב־/
if (strpos($fileName, '..') !== false || $fileName[0] === '/') {
    trigger_error("Invalid command path - $fileName (blocked for security)");
    exit;
}
// --- Security Fix End --- // Fixed by pablo Rotem for latest php 14.11.2025


include $fileName;

$xmlResponse  = "<?xml version='1.0' encoding='UTF-8' ?>\n";
$xmlResponse .= "<interuse>\n";
$xmlResponse .= "    <response>\n";

if (function_exists($functionName)) {
    $dummyTags = xmlParser_getDummyTags($xmlRequest);

    $xmlResponse .= "<responseType>Success</responseType>\n";
    $xmlResponse .= "<command>$command</command>\n";
    $xmlResponse .= "<requestId>$requestId</requestId>\n";
    $xmlResponse .= "<responseData>\n$dummyTags";

    $commandXml = call_user_func($functionName, $xmlRequest);

    if ($commandXml != "") {
        if ($domainId == 135) {
            $hebKeys = explode(",", "à,á,â,ã,ä,å,æ,ç,è,é,ê,ë,ì,í,î,ï,ð,ñ,ò,ó,ô,õ,ö,÷,ø,ù,ú");
            $hebVals = explode(",", ",,,,,,,,,,,,,,,,,,,,,,,,,,");
            $commandXml = str_replace($hebKeys, $hebVals, $commandXml);
        }

        if (empty($isUTF8)) {
            $xmlResponse .= iconv("windows-1255", "utf-8//IGNORE", $commandXml);
        } else {
            $xmlResponse .= $commandXml;
        }
    }

    $xmlResponse .= "</responseData>\n";
} else {
    $xmlResponse .= "<responseType>Error</responseType>\n";
    $xmlResponse .= "<message>Command '$command' does not exist</message>\n";
}

$xmlResponse .= "    </response>\n";
$xmlResponse .= "</interuse>";

if ($domainId == 304 || xmlParser_getValue($xmlRequest, "debugXml") == 1) {
    if (!empty($ibosHomeDir)) {
        $fileHandle = fopen("$ibosHomeDir/xmlDebug/in.xml", "w");
        fwrite($fileHandle, $HTTP_RAW_POST_DATA);
        fclose($fileHandle);

        $fileHandle = fopen("$ibosHomeDir/xmlDebug/out.xml", "w");
        fwrite($fileHandle, $xmlResponse);
        fclose($fileHandle);
    }
}

echo $xmlResponse;
