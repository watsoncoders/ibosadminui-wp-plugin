<?php
/**
 * תיקון תאימות PHP 8.3/8.4 ל-commonAdmin.php
 * Author: pablo rotem
 */

declare(strict_types=1);

$input = __DIR__ . '/commonAdmin.php';
if (!is_file($input)) {
    fwrite(STDERR, "לא נמצא קובץ: {$input}\n");
    exit(1);
}

$code = file_get_contents($input);
if ($code === false) {
    fwrite(STDERR, "לא ניתן לקרוא את הקובץ.\n");
    exit(1);
}

/**
 * 1) החלפה של גישה עם {} (לא נתמך ב-PHP 8.4) ל-[].
 *    זה מכסה בדיוק את התבניות כמו: $str[$i]{0} , $str[$i]{1} ...
 */
$code = preg_replace('/(\$[A-Za-z_][A-Za-z0-9_]*\[[^\]]+\])\{(\d+)\}/', '$1[$2]', $code);

/**
 * 2) תיקון באג תחביר נפוץ מהקטע שהדבקת:
 *    ord($str[$i]{3)-128  ->  ord($str[$i][3])-128
 *    (אם מופיע בצורה הזאת בקובץ)
 */
$code = str_replace('ord($str[$i]{3)-128', 'ord($str[$i][3])-128', $code);

/**
 * 3) שיפור קטן (לא חובה אבל מומלץ): בקטע UTF-8 הרבה פעמים יש chlen לא נכון.
 *    אם אצלך יש chlen=2 גם ב-3 בתים / 4 בתים — זה יוצר דילוג שגוי.
 *    לכן נתקן רק אם נמצא את הדפוסים האלה בדיוק.
 */
$code = str_replace('$chlen = 2;', '$chlen = 2;', $code); // לא משנה 2-בתים

// 3-בתים: בדרך כלל צריך להיות 3
$code = preg_replace(
    '/(\$ud\s*=\s*\(ord\(\$str\[\$i\]\[0\]\)\-224\)\*4096\s*\+\s*\(ord\(\$str\[\$i\]\[1\]\)\-128\)\*64\s*\+\s*\(ord\(\$str\[\$i\]\[2\]\)\-128\);\s*)\$chlen\s*=\s*2;/',
    '$1$chlen = 3;',
    $code
);

// 4-בתים: בדרך כלל צריך להיות 4
$code = preg_replace(
    '/(\$ud\s*=\s*\(ord\(\$str\[\$i\]\[0\]\)\-240\)\*262144\s*\+\s*\(ord\(\$str\[\$i\]\[1\]\)\-128\)\*4096\s*\+\s*\(ord\(\$str\[\$i\]\[2\]\)\-128\)\*64\s*\+\s*\(ord\(\$str\[\$i\]\[3\]\)\-128\);\s*)\$chlen\s*=\s*\d+;/',
    '$1$chlen = 4;',
    $code
);

$out = __DIR__ . '/commonAdmin.php.php84fixed';
if (file_put_contents($out, $code) === false) {
    fwrite(STDERR, "נכשל לכתוב קובץ פלט: {$out}\n");
    exit(1);
}

echo "הצלחה ✅ נוצר קובץ מתוקן: {$out}\n";
echo "כדי להחליף: mv commonAdmin.php commonAdmin.php.bak && mv commonAdmin.php.php84fixed commonAdmin.php\n";
