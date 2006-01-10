<?php

/**
 * example usage (CLI)
 *
 * @package Text_LanguageDetect
 * @version CVS: $Id$
 */

require_once 'Text/LanguageDetect.php';

$l = new Text_LanguageDetect;

$stdin = fopen('php://stdin', 'r');

echo "Supported languages:\n";
$langs = $l->getLanguages();
if (PEAR::isError($langs)) {
    die($langs->getMessage());
}
sort($langs);
echo join(', ', $langs);

echo "\ntotal ", count($langs), "\n\n";

while ($line = fgets($stdin)) {
    $result = $l->detect($line, 4);
    if (PEAR::isError($result)) {
        echo $result->getMessage(), "\n";
    } else {
        print_r($result);
    }
}

fclose($stdin);
unset($l);

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

?>
