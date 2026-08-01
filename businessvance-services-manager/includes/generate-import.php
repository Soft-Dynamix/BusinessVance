<?php
// Generator script - run once then delete
if (php_sapi_name() !== 'cli') die('Run from CLI');

generate_import_file();

echo "Done.\n";

function generate_import_file() {
    $lines = [];
    $t = "\t"; // tab
    $tt = "\t\t";
    $ttt = "\t\t\t";
    $tttt = "\t\t\t\t";
    $ttttt = "\t\t\t\t\t";

    $lines[] = '<?php';
    $lines[] = '/**';
    $lines[] = ' * BusinessVance Services Manager - Questionnaire Import';
    $lines[] = ' *';
    $lines[] = ' * @package BusinessVance_Services_Manager';
    $lines[] = ' * @since   1.0.0';
    $lines[] = ' */';
    $lines[] = '';
    $lines[] = 'if ( ! defined( \'ABSPATH\' ) ) {';
    $lines[] = "${t}exit;";
    $lines[] = '}';
    $lines[] = '';
    $lines[] = 'class BV_Questionnaire_Import {';
    $lines[] = '';echo implode("\n", $lines) . "\n";
}
