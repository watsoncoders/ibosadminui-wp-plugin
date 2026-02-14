<?php
/**
 * commands_general.php - Basic Admin Screens
 */

/**
 * The default landing page for the admin panel.
 */
function userHomePage() {
    $domainRow = commonGetDomainRow(); // Fetch site details
    $siteName = $domainRow['domainName'] ?? "האתר שלך";

    echo "<div style='direction:rtl; font-family:Arial; padding:25px;'>";
    echo "<h1 style='color:#86b300;'>ברוכים הבאים למערכת i-BOS</h1>";
    echo "<p style='font-size:16px;'>אתה מנהל כעת את: <strong>$siteName</strong></p>";
    echo "<hr style='border:1px dashed #ccc;'>";
    echo "<div style='margin-top:20px; padding:15px; background:#f9f9f9; border-right:5px solid #86b300;'>";
    echo "<h3>עדכונים אחרונים</h3>";
    echo "<ul><li>ההיררכיה של המיקומים עודכנה בהצלחה.</li><li>המערכת הותאמה ל-PHP 8.3 ולעבודה בתוך וורדפרס.</li></ul>";
    echo "</div></div>";
}

/**
 * The 'Settings' (הגדרות) screen logic.
 */
function showSettings() {
    $domainRow = commonGetDomainRow();

    echo "<div style='direction:rtl; font-family:Arial; padding:25px;'>";
    echo "<h2>הגדרות מערכת</h2>";
    echo "<form style='background:#fff; padding:20px; border:1px solid #ddd;'>";
    echo "<label>שם הדומיין:</label><br>";
    echo "<input type='text' value='" . esc_attr($domainRow['domainName'] ?? '') . "' style='width:300px; padding:5px;' readonly><br><br>";
    echo "<label>מצב תחזוקה:</label><br>";
    echo "<input type='checkbox' " . ($domainRow['isStatic'] == '1' ? 'checked' : '') . "> הפעל מצב סטטי<br><br>";
    echo "<button type='button' style='background:#86b300; color:#fff; border:0; padding:10px 20px; cursor:pointer;'>שמור שינויים</button>";
    echo "</form></div>";
}