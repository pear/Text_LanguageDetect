<?php

/**
 * example usage (web)
 *
 * @package Text_LanguageDetect
 * @version CVS: $Id$
 */

// browsers will encode multi-byte characters wrong unless they think the page is utf8-encoded
header('Content-type: text/html; charset=utf-8',true);

require_once 'Text/LanguageDetect.php';

$l = new Text_LanguageDetect;

?>
<html>
<head>
<title>Text_LanguageDetect demonstration</title>
</head>
<body>
<h2>Text_LanguageDetect</h2>
<?
echo "<small>supported languages:\n";
$langs = $l->getLanguages();
sort($langs);
foreach ($langs as $lang) {
	echo ucfirst($lang), ', ';
	$i++;
}

echo "<br />total $i</small><br /><br />";

?>
<form method="post">
Enter text to identify language (at least a couple of sentences):<br />
<textarea name="q" wrap="virtual" cols="80" rows="8"><?= stripslashes($_REQUEST['q']) ?></textarea>
<br />
<input type="submit" value="Submit" />
</form>
<?
if (isset($_REQUEST['q']) && $len = strlen($_REQUEST['q'])) {
	if ($len < 50) { # this value was picked somewhat arbitrarily
		echo "warning: string not very long ($len chars)<br />\n";
	}

	$result = $l->detectConfidence($_REQUEST['q']);

	if ($result == null) {
		echo "Text_LanguageDetect cannot identify this piece of text.\n";
	} else {
		echo "Text_LanguageDetect thinks this text is written in <b>{$result['language']}</b> ({$result['similarity']}, {$result['confidence']})\n";
	}
}

unset($l);

?>
</body></html>
