<?php
$src = file_get_contents(__DIR__ . '/../dashboard.legacy.php');
if (!preg_match('/\?>\r?\n(.*)/s', $src, $m)) {
    fwrite(STDERR, "no html\n");
    exit(1);
}
$html = $m[1];

$markers = [
    'list'          => ['LIST', 'ADD'],
    'add'           => ['ADD', 'EDIT'],
    'edit'          => ['EDIT', 'RECORD SALE'],
    'sale_add'      => ['RECORD SALE', 'SALES REPORT'],
    'sales'         => ['SALES REPORT', 'SALES SUMMARY'],
    'report'        => ['SALES SUMMARY', 'INVENTORY DETAILS'],
    'inventory'     => ['INVENTORY DETAILS', 'BILL / INVOICE'],
    'bill'          => ['BILL / INVOICE', 'SYSTEM NOTIFICATIONS'],
    'notifications' => ['SYSTEM NOTIFICATIONS', null],
];

$dir = __DIR__ . '/../views';
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

foreach ($markers as $name => [$start, $end]) {
    $startPat = '/<\?php \/\/ ===================== ' . preg_quote($start, '/') . '.*?\?>\s*/s';
    if (!preg_match($startPat, $html, $sm, PREG_OFFSET_CAPTURE)) {
        echo "missing start $start\n";
        continue;
    }
    $from = $sm[0][1] + strlen($sm[0][0]);

    if ($end === null) {
        $to = strpos($html, '</main>', $from);
        $chunk = substr($html, $from, $to - $from);
    } else {
        $endPat = '/<\?php \/\/ ===================== ' . preg_quote($end, '/') . '/';
        if (!preg_match($endPat, $html, $em, PREG_OFFSET_CAPTURE, $from)) {
            echo "missing end $end\n";
            continue;
        }
        $chunk = substr($html, $from, $em[0][1] - $from);
    }

    $chunk = preg_replace('/^\s*<\?php if \(\$view === .*?: \?>\s*/s', '', $chunk);
    $chunk = preg_replace('/^\s*<\?php elseif \(\$view === .*?: \?>\s*/s', '', $chunk);
    $chunk = preg_replace('/\s*<\?php endif; \?>\s*$/s', '', $chunk);
    $chunk = trim($chunk) . "\n";

    file_put_contents("$dir/$name.php", $chunk);
    echo "wrote $name.php (" . strlen($chunk) . " bytes)\n";
}

// Layout: from start until LIST marker
if (preg_match('/^(.*?)<\?php \/\/ ===================== LIST/s', $html, $hm)) {
    file_put_contents("$dir/_shell_start.php", $hm[1]);
    echo 'wrote _shell_start.php (' . strlen($hm[1]) . " bytes)\n";
}

// Layout end after main
if (preg_match('/<\/main>\s*(.*)$/s', $html, $fm)) {
    file_put_contents("$dir/_shell_end.php", "</main>\n" . $fm[1]);
    echo 'wrote _shell_end.php (' . strlen($fm[1]) . " bytes)\n";
}

echo "done\n";
