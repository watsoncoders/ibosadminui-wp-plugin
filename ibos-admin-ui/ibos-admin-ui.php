<?php
/**
 * Plugin Name: i-BOS Admin UI (Bridge via iFrame)
 * Description: מציג את מערכת i-BOS בתוך לוח הבקרה של וורדפרס באמצעות iFrame (מניעת קונפליקטים JS/CSS).
 * Version: 3.0.1
 * Author: pablo rotem
 */

if (!defined('ABSPATH')) exit;

define('IBOS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('IBOS_30_DIR', IBOS_PLUGIN_DIR . '3.0/');
define('IBOS_30_PHP_DIR', IBOS_30_DIR . 'php/');
define('IBOS_30_URL', plugin_dir_url(__FILE__) . '3.0/');

add_action('admin_menu', function () {
    add_menu_page(
        'i-BOS Admin',
        'i-BOS Admin',
        'manage_options',
        'ibos-admin-panel',
        'ibos_render_admin_page',
        'dashicons-layout',
        2
    );
});

function ibos_render_admin_page(): void
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions.'));
    }

    $mainPhp = IBOS_30_PHP_DIR . 'main.php';
    $commonAdmin = IBOS_30_PHP_DIR . 'commonAdmin.php';

    if (!is_file($mainPhp) || !is_file($commonAdmin)) {
        echo '<div class="notice notice-error"><p>חסר קובץ i-BOS: בדוק שקיימים 3.0/php/main.php ו-3.0/php/commonAdmin.php בתוך התוסף.</p></div>';
        return;
    }

    // נטען פונקציות DB של iBOS (mysqli וכו')
    require_once $commonAdmin;

    // יצירת/הבטחת sessionCode תקין בטבלת sessions
    $sessionCode = ibos_ensure_session_code();

    // מציגים את המערכת המקורית ב-iframe כדי לשמור על העיצוב/תפריטים/JS
    $src = IBOS_30_URL . 'php/main.php?sessionCode=' . rawurlencode($sessionCode);

    echo '<div class="wrap" style="padding:0;margin:0">';
    echo '<h1 style="margin: 12px 0;">i-BOS Admin</h1>';

    echo '<iframe
            src="' . esc_url($src) . '"
            style="width:100%;height:calc(100vh - 160px);border:1px solid #ddd;background:#fff;"
            frameborder="0"
            referrerpolicy="no-referrer"
          ></iframe>';

    echo '</div>';
}

/**
 * יוצר sessionCode אמיתי בטבלת sessions כדי ש-commonValidateSession לא יפיל את המערכת.
 * מחבר: pablo rotem
 */
function ibos_ensure_session_code(): string
{
    // אפשר לבחור משתמש iBOS "קבוע" (למשל id=1) כסופר-אדמין בתוך ה-iframe
    $memberId = 1;
    $isSuper  = 1;

    // קוד סשן קצר-ארוך בסגנון iBOS
    $code = ibos_random_code(49);

    // התחברות DB דרך פונקציות iBOS
    $mysqlHandle = commonConnectToDB();

    // מכניסים סשן חדש
    $codeEsc = mysqli_real_escape_string($mysqlHandle, $code);

    // ניקוי סשנים ישנים (כמו המקור)
    $minDate = date("Y-m-d H:i:00", strtotime("-6 hours"));
    $minDateEsc = mysqli_real_escape_string($mysqlHandle, $minDate);
    @commonDoQuery("DELETE FROM sessions WHERE creationTime < '{$minDateEsc}'");

    // הכנסה
    @commonDoQuery(
        "INSERT INTO sessions (id, code, memberId, isSuper, creationTime, lastCheck)
         VALUES (NULL, '{$codeEsc}', " . (int)$memberId . ", " . (int)$isSuper . ", NOW(), NOW())"
    );

    commonDisconnect($mysqlHandle);

    return $code;
}

/**
 * מחולל קוד בטוח (hex) כדי להחליף randomCode אם צריך.
 * מחבר: pablo rotem
 */
function ibos_random_code(int $len = 49): string
{
    $bytes = (int)ceil($len / 2);
    return substr(bin2hex(random_bytes($bytes)), 0, $len);
}
