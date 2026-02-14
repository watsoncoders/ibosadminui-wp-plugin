<?php
/**
 * Plugin Name: iBOS Admin UI (Mock)
 * Description: שלד UI בסגנון i-bos בתוך לוח הבקרה של וורדפרס (RTL). בשלב זה: עיצוב ותצוגה בלבד.
 * Version: 0.1.0
 * Author: pablo rotem
 * Requires at least: 6.0
 * Requires PHP: 8.3
 */

if (!defined('ABSPATH')) {
    exit;
}

final class IBOS_Admin_UI_PabloRotem
{
    public const SLUG = 'ibos-admin-ui';

    public function __construct()
    {
        // 1. Load the legacy logic immediately so its functions are available to WordPress
        $common_path = plugin_dir_path(__FILE__) . '3.0/php/commonAdmin.php';
        if (file_exists($common_path)) {
            require_once($common_path);
        }

        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

        // 2. Register a handler for the AJAX commands sent from your admin.js
        add_action('wp_ajax_ibos_cmd', [$this, 'handle_ibos_command']);
    }

    public function register_menu(): void
    {
        add_menu_page(
            'i-bos',
            'i-bos',
            'manage_options',
            self::SLUG,
            [$this, 'render_page'],
            'dashicons-screenoptions',
            2
        );
    }

    /**
     * This new function handles the background requests from your UI
     * It connects to the israeli_admin database and runs legacy server logic.
     */
    public function handle_ibos_command(): void
    {
        // Set context for legacy scripts
        global $sessionCode, $siteId;
        $sessionCode = "WP_INTEGRATED_ADMIN";
        $siteId = 1; // Default for hashuk-2

        // Authenticate via the bridge we built in commonAdmin.php
        if (function_exists('commonValidateSession')) {
            commonValidateSession();
        }

        // Include the legacy server router
        $server_path = plugin_dir_path(__FILE__) . '3.0/php/server.php';
        if (file_exists($server_path)) {
            include($server_path);
        }

        wp_die(); // Required for WordPress AJAX
    }

    public function enqueue_assets(string $hook): void
    {
        // נטען רק בעמוד של התוסף
        if ($hook !== 'toplevel_page_' . self::SLUG) {
            return;
        }

        $ver = '0.1.0';
        $base = plugin_dir_url(__FILE__) . 'assets/';

        wp_enqueue_style('ibos-admin-ui-css', $base . 'admin.css', [], $ver);

        // וורדפרס כבר מגיע עם jQuery (לא משתמשים ב-1.8.2 הישן)
        wp_enqueue_script('jquery');
        wp_enqueue_script('ibos-admin-ui-js', $base . 'admin.js', ['jquery'], $ver, true);

        wp_localize_script('ibos-admin-ui-js', 'IBOS_UI', [
            'siteName'  => get_bloginfo('name'),
            'siteUrl'   => home_url('/'),
            'userName'  => wp_get_current_user()->display_name ?: 'משתמש',
            'lastLogin' => date_i18n('d.m.Y , H:i'),
        ]);
    }

    public function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('אין הרשאה.');
        }

        // הערה: זה UI בלבד. בהמשך נחבר פעולות/נתיבים/תוכן דינמי.
        ?>
        <div class="ibos-wrap" dir="rtl">
            <div class="ibos-header">
                <div class="ibos-header-in">
                    <div class="ibos-logo" title="i-Bos">
                        <span class="ibos-logo-mark">i</span>
                        <span class="ibos-logo-text">-BOS</span>
                    </div>

                    <div class="ibos-header-top">
                        <div class="ibos-header-top-left">
                            <a class="ibos-exit" href="<?php echo esc_url(admin_url()); ?>">
                                <span class="dashicons dashicons-migrate"></span>
                                יציאה
                            </a>
                        </div>

                        <div class="ibos-header-top-right">
                            <div class="ibos-managed-title">האתר המנוהל<span class="ibos-sep"></span></div>
                            <div class="ibos-sitebox">
                                <a href="<?php echo esc_url(home_url('/')); ?>" target="_blank" rel="noopener">
                                    <?php echo esc_html(parse_url(home_url('/'), PHP_URL_HOST) ?: home_url('/')); ?>
                                </a>
                            </div>

                            <div class="ibos-hello">
                                <span class="ibos-hello-strong">שלום</span>
                                <span class="ibos-user" id="ibosUserName"><?php echo esc_html(wp_get_current_user()->display_name ?: 'משתמש'); ?></span>
                                <span class="ibos-sep"></span>
                                <span class="ibos-lastlogin">מועד כניסתך האחרון: <span id="ibosLastLogin"><?php echo esc_html(date_i18n('d.m.Y , H:i')); ?></span></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ibos-mainmenu">
                <div class="ibos-mainmenu-in">

                    <!-- כפתור “בשימוש לאחרונה” למובייל -->
                    <button type="button" id="ibosRecentBtn" class="ibos-menubtn">
                        בשימוש לאחרונה <span class="ibos-down">▾</span>
                    </button>

                   <div class="ibos-topnav">
    <button class="ibos-menubtn" data-page="settings">הגדרות <span class="ibos-down">▾</span></button>
    <button class="ibos-menubtn" data-page="design">עיצוב <span class="ibos-down">▾</span></button>
    <button class="ibos-menubtn" data-page="locations">תוכן בסיסי <span class="ibos-down">▾</span></button>
    <button class="ibos-menubtn" data-page="extended_content">תוכן מורחב <span class="ibos-down">▾</span></button>
    <button class="ibos-menubtn" data-page="lists">רשימות <span class="ibos-down">▾</span></button>
    <button class="ibos-menubtn" data-page="boards">לוחות <span class="ibos-down">▾</span></button>
    <button class="ibos-menubtn" data-page="social">רשתות חברתיות <span class="ibos-down">▾</span></button>
    <button class="ibos-menubtn" data-page="ecommerce">מסחר אלקטרוני <span class="ibos-down">▾</span></button>
    <button class="ibos-menubtn" data-page="crm">קשרי לקוחות <span class="ibos-down">▾</span></button>
    <button class="ibos-menubtn" data-page="addons">תוספות ייחודיות <span class="ibos-down">▾</span></button>
</div>

            <div class="ibos-body">
                <!-- “בשימוש לאחרונה” (צד ימין בדסקטופ) -->
                <aside id="ibosFloatMenu" class="ibos-floatmenu" aria-label="בשימוש לאחרונה">
                    <div class="ibos-floatmenu-title">בשימוש לאחרונה</div>

                    <button class="ibos-floatlink" data-page="forums">ניהול פורומים</button>
                    <button class="ibos-floatlink" data-page="galleries">ניהול גלריות</button>
                    <button class="ibos-floatlink" data-page="design-files">ניהול קבצי עיצוב</button>
                    <button class="ibos-floatlink" data-page="categories">ניהול קטגוריות</button>
                    <button class="ibos-floatlink" data-page="layouts">ניהול תבניות עיצוב</button>
                    <button class="ibos-floatlink" data-page="langs">שפות נתמכות</button>

                    <button class="ibos-floatlink ibos-green" data-page="exit">יציאה</button>
                </aside>

                <!-- אזור מרכזי -->
                <main class="ibos-mainhtml">
                    <div class="ibos-framewrap">
                        <iframe
                            id="ibosMainFrame"
                            name="ibosMainFrame"
                            frameborder="0"
                            width="980"
                            height="900"
                            src="about:blank"
                            title="אזור תוכן">
                        </iframe>
                    </div>

                    <div class="ibos-emptyhint">
                        <h2>הודעות ועדכונים</h2>
                        <p>זהו שלד UI בלבד. בשלב הבא נחבר את ה-iframe/דפים/פעולות PHP.</p>
                    </div>
                </main>
            </div>
        </div>
        <?php
    }
}

new IBOS_Admin_UI_PabloRotem();
