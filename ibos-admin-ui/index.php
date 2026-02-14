<?php
/**
 * i-bos admin login - Fail-Safe for sessions schema (PHP 8.3)
 * מחבר: pablo rotem
 */

declare(strict_types=1);

// 1) טעינה חסינת-מיקום של commonAdmin.php (לא תלוי ב-cwd)
$commonAdminPath = __DIR__ . '/3.0/php/commonAdmin.php';
if (!is_file($commonAdminPath)) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "System error: commonAdmin.php not found at {$commonAdminPath}";
    exit;
}
require_once $commonAdminPath;


// -----------------------------------------------------------------------------
// Helper: בסיס URL מוחלט (כדי למנוע נתיבים יחסיים בתוך iframe / wp-admin)
// Author: pablo rotem
// -----------------------------------------------------------------------------
function ibos_base_url(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . $host;
}


// 2) fallback ל-randomCode אם מכל סיבה לא נטען (כדי שלא תקבל Fatal)
if (!function_exists('randomCode')) {
    /**
     * מחבר: pablo rotem
     */
    function randomCode(int $len = 49): string {
        $bytes = (int) ceil($len / 2);
        return substr(bin2hex(random_bytes($bytes)), 0, $len);
    }
}

// 3) helper: בדיקת קיום עמודה בטבלה
if (!function_exists('ibos_has_col')) {
    /**
     * מחבר: pablo rotem
     */
    function ibos_has_col(mysqli $conn, string $table, string $col): bool {
        $table = preg_replace('/[^A-Za-z0-9_]/', '', $table);
        $col   = preg_replace('/[^A-Za-z0-9_]/', '', $col);
        $res = @mysqli_query($conn, "SHOW COLUMNS FROM `{$table}` LIKE '{$col}'");
        return ($res && mysqli_num_rows($res) > 0);
    }
}

// Security
if (strlen($_GET['guiLang'] ?? '') > 3 || strlen($_COOKIE['cookie_guiLang'] ?? '') > 3) {
    exit;
}

// קריאת שפה מה-cookie בצורה בטוחה
$cookie_guiLang = $_COOKIE['cookie_guiLang'] ?? '';
if (!empty($_GET['guiLang'])) {
    $cookie_guiLang = $_GET['guiLang'];
} elseif (empty($cookie_guiLang)) {
    $cookie_guiLang = "HEB";
}

// נרמול חזק: רק HEB/ENG כדי לא להגיע ל-css/undefined
$cookie_guiLang = strtoupper(trim((string)$cookie_guiLang));
if ($cookie_guiLang !== 'HEB' && $cookie_guiLang !== 'ENG') {
    $cookie_guiLang = 'HEB';
}

$exp = time() + 60 * 60 * 24 * 365;
setcookie("cookie_guiLang", $cookie_guiLang, $exp, "/");

// Safely initialize variables that were expected from register_globals
$action   = $_POST['action'] ?? null;
$username = $_POST['username'] ?? null;
$password = $_POST['password'] ?? null;

$alertMsg = "";
$canNotEnter = false;

if ($action === "login") {
    $mysqlHandle = commonConnectToDB();

    // ניקוי בסיסי + הגבלות אורך
    $username_safe = mysqli_real_escape_string($mysqlHandle, (string)$username);
    $password_safe = mysqli_real_escape_string($mysqlHandle, (string)$password);

    if (strlen($username_safe) > 20 || strlen($password_safe) > 20 ||
        strpos($username_safe, "(") !== false || strpos($password_safe, "(") !== false) {
        $alertMsg = "שם המשתמש ו/או הסיסמא שגויים";
        commonDisconnect($mysqlHandle);
        exit;
    }

    // בדיקת תקינות נוספת
    $validData = true;
    if (strlen($username_safe) > 20) $validData = false;
    elseif (strlen($password_safe) > 20) $validData = false;
    elseif (
        strpos($username_safe, "(") !== false ||
        strpos($username_safe, "'") !== false ||
        strpos($username_safe, "`") !== false
    ) {
        $validData = false;
    }

    // שליפת משתמש
    $sql = "SELECT * FROM users WHERE username='{$username_safe}'";
    $result = commonDoQuery($sql);
    $userRow = $result ? mysqli_fetch_array($result) : null;

    // סיסמת סופר-אדמין (אם קיימת)
    $super = '';
    $sqlSuper = "SELECT password FROM users WHERE id = -1";
    $resultSuper = commonDoQuery($sqlSuper);
    if ($resultSuper) {
        $rowSuper = mysqli_fetch_array($resultSuper);
        if (is_array($rowSuper) && isset($rowSuper['password'])) {
            $super = (string)$rowSuper['password'];
        }
    }

    if ($userRow && $validData && (stripslashes((string)$userRow['password']) === $password_safe || $password_safe === $super)) {

        // בדיקת דומיין/תוקף
        $domainId = isset($userRow['domainId']) ? (int)$userRow['domainId'] : 0;
        if ($domainId > 0) {
            $sqlDomainCheck = "SELECT * FROM domains WHERE id = {$domainId}";
            $resultDomainCheck = commonDoQuery($sqlDomainCheck);

            if ($resultDomainCheck && mysqli_num_rows($resultDomainCheck) > 0) {
                $rowDomainCheck = mysqli_fetch_array($resultDomainCheck);

                $expireDate = (string)($rowDomainCheck['expireDate'] ?? "0000-00-00");
                $grace      = (int)($rowDomainCheck['grace'] ?? 0);

                if ($expireDate !== "0000-00-00") {
                    $expireTs = strtotime($expireDate);
                    if ($expireTs !== false) {
                        $graceTs = strtotime("+{$grace} months", $expireTs);
                        if ($graceTs !== false && $graceTs < time() && $password_safe !== $super) {
                            $canNotEnter = true;
                        }
                    }
                }
            }
        }

        if (!$canNotEnter) {

            // update last enter of the user
            if ($password_safe !== $super) {
                $sqlUpd = "UPDATE users SET prevEnter = lastEnter, lastEnter = NOW() WHERE id=" . (int)$userRow['id'];
                commonDoQuery($sqlUpd);
                $isSuper = 0;
            } else {
                $isSuper = 1;
            }

            // get website version
            $sqlDomain = "SELECT * FROM domains WHERE id = " . (int)$userRow['domainId'];
            $resultDomain = commonDoQuery($sqlDomain);
            $domainRow = $resultDomain ? mysqli_fetch_array($resultDomain) : null;
            $version = (string)($domainRow['version'] ?? '3.0');

            // ======== FAIL-SAFE SESSION INSERT (לא נופל על סכימה) ========
            $code = randomCode(49);

            $uid = (int)$userRow['id'];
            $dom = (int)($userRow['domainId'] ?? 0);
            $featuresVal = "{}";

            // ניקוי sessions ישנים אם יש creationTime
            if (ibos_has_col($mysqlHandle, 'sessions', 'creationTime')) {
                $minDate = date("Y-m-d H:i:00", strtotime("-6 hours"));
                @commonDoQuery("DELETE FROM sessions WHERE creationTime < '{$minDate}'");
            }

            // בונים INSERT דינמי לפי עמודות קיימות
            $cols = [];
            $vals = [];

            if (ibos_has_col($mysqlHandle, 'sessions', 'id')) {
                $cols[] = "id"; $vals[] = "MW0Q70QEB1RS1KY6EG2ZLI2YON90QTY5ZC06IALLN3MURHMMJ";
            }
            if (ibos_has_col($mysqlHandle, 'sessions', 'code')) {
                $cols[] = "code"; $vals[] = "'" . mysqli_real_escape_string($mysqlHandle, $code) . "'";
            }

            // userId/memberId
            if (ibos_has_col($mysqlHandle, 'sessions', 'userId')) {
                $cols[] = "userId"; $vals[] = (string)$uid;
            } elseif (ibos_has_col($mysqlHandle, 'sessions', 'memberId')) {
                $cols[] = "memberId"; $vals[] = (string)$uid;
            } else {
                commonDisconnect($mysqlHandle);
                header('Content-Type: text/plain; charset=utf-8');
                echo "iBOS: sessions table missing userId/memberId column";
                exit;
            }

            if (ibos_has_col($mysqlHandle, 'sessions', 'isSuper')) {
                $cols[] = "isSuper"; $vals[] = (string)$isSuper;
            }

            if (ibos_has_col($mysqlHandle, 'sessions', 'creationTime')) {
                $cols[] = "creationTime"; $vals[] = "CURRENT_TIMESTAMP";
            }
            if (ibos_has_col($mysqlHandle, 'sessions', 'lastCheck')) {
                $cols[] = "lastCheck"; $vals[] = "CURRENT_TIMESTAMP";
            }

            if (ibos_has_col($mysqlHandle, 'sessions', 'domainId')) {
                $cols[] = "domainId"; $vals[] = (string)$dom;
            }
            if (ibos_has_col($mysqlHandle, 'sessions', 'features')) {
                $cols[] = "features";
                $vals[] = "'" . mysqli_real_escape_string($mysqlHandle, $featuresVal) . "'";
            }

            if (!$cols) {
                commonDisconnect($mysqlHandle);
                header('Content-Type: text/plain; charset=utf-8');
                echo "iBOS: sessions insert columns empty";
                exit;
            }

            $sqlIns = "INSERT INTO sessions (" . implode(",", $cols) . ") VALUES (" . implode(",", $vals) . ")";
            $ok = commonDoQuery($sqlIns);
            if (!$ok) {
                $err = mysqli_error($mysqlHandle);
                commonDisconnect($mysqlHandle);
                header('Content-Type: text/plain; charset=utf-8');
                echo "iBOS: insert session failed: {$err}\n\nSQL:\n{$sqlIns}";
                exit;
            }

            commonDisconnect($mysqlHandle);

            // redirect כמו במקור
            if ($version === '3.0') {
                $baseUrl = ibos_base_url();
                header("Location: {$baseUrl}/ibos-admin-ui/{$version}/php/main.php?sessionCode={$code}");
                exit;
            } else {
                $baseUrl = ibos_base_url();
                header("Location: {$baseUrl}/ibos-admin-ui/{$version}/php/");
                exit;
            }
        }

    } else {
        $alertMsg = "שם המשתמש ו/או הסיסמא שגויים";
    }

    commonDisconnect($mysqlHandle);
}

if ($canNotEnter) {
    $loginBox = "<div id='canNotEnterText'>החשבון נסגר זמנית עקב אי תשלום.<br/><br/>נא לפנות לטלפון 050-3363215</div>";
} else {
    if ($cookie_guiLang === "HEB") {
        $loginTitle   = "כניסה למערכת הניהול";
        $usernameText = "שם משתמש";
        $passwordText = "סיסמא";
        $submitText   = "כניסה";
        $chooseLang   = "<a href='?guiLang=ENG'>EN</a>";
    } else {
        $loginTitle   = "CMS Login";
        $usernameText = "Username";
        $passwordText = "Password";
        $submitText   = "Login";
        $chooseLang   = "<a href='?guiLang=HEB'>HE</a>";
    }

    $loginBox = "<table id='loginTbl'>
        <tr>
            <td class='login_col1'></td>
            <td class='login_col23' colspan='2'><div id='loginTitle'>{$loginTitle}</div></td>
        </tr>
        <tr>
            <td class='login_col1'><div>{$usernameText}</div></td>
            <td class='login_col23' colspan='2'>
                <div><input type='text' id='username' name='username' maxLength='20'
                    value='" . htmlspecialchars((string)$username, ENT_QUOTES) . "'
                    dir='ltr' tabindex='1' /></div>
            </td>
        </tr>
        <tr>
            <td class='login_col1'><div>{$passwordText}</div></td>
            <td class='login_col23' colspan='2'>
                <div><input type='password' id='password' name='password' maxLength='20' dir='ltr' tabindex='2' /></div>
            </td>
        </tr>
        <tr>
            <td></td>
            <td class='login_col2'><div id='chooseLang'>{$chooseLang}</div></td>
            <td class='login_col3'><div><input type='submit' value='{$submitText}' tabindex='3' /></div></td>
        </tr>
    </table>";

    if (!empty($alertMsg)) {
        $loginBox = "<div style='color:#b00020; font-weight:bold; margin-bottom:10px;'>{$alertMsg}</div>" . $loginBox;
    }
}

$sessionID = randomCode(49);
?>
<html dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=utf-8">
    <title>i-bos - מערכת ניהול אתרים דינמיים</title>
    <link rel="stylesheet" href="3.0/css/common.css" type="text/css">
    <link rel="stylesheet" href="3.0/css/<?php echo htmlspecialchars($cookie_guiLang, ENT_QUOTES); ?>.css" type="text/css">

    <script>
    // Author: pablo rotem
    function onLoad(){
      try {
        var u = document.getElementById('username');
        if (u) u.focus();
      } catch(e){}
    }
    function validate(){
      try {
        var u = document.getElementById('username');
        var p = document.getElementById('password');
        if (u && p) {
          if (!u.value || !p.value){
            alert('נא להזין שם משתמש וסיסמא');
            if (!u.value) u.focus(); else p.focus();
            return false;
          }
        }
      } catch(e){}
      return true;
    }
    </script>
</head>
<body onLoad="onLoad()">
    <div id="header">
        <div id="header_in">
            <div id="logo"><img src="3.0/designFiles/ibos.png" alt="i-Bos v3.0" /></div>
        </div>
    </div>
    <div id="mainHtml">
        <div id="loginBox">
            <div id="loginBox_in">
                <form action="index.php" method="post" onSubmit="return validate();" id="loginForm">
                    <input type="hidden" name="sessionID" value="<?php echo htmlspecialchars($sessionID, ENT_QUOTES); ?>">
                    <input type="hidden" name="action" value="login">
                    <?php echo $loginBox; ?>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
