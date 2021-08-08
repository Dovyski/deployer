<?php

$shortopts  = '';
$shortopts .= 'h::';
$shortopts .= 'b::';
$shortopts .= 'c::';
$shortopts .= 'f::';

$longopts  = array(
    "app-dir:",       
    "backup-dir:",
    "help::",
    "backup-only::",
    "update-cmd::",
    "cautious::",
    "force::",
);

$options = getopt($shortopts, $longopts);

if (count($options) == 0 || isset($options['h']) || isset($options['help'])) {
    echo "Usage: php " . basename(__FILE__) . " [options]\n";
    echo "Options:\n";
    echo "  -b, --backup-only\n";
    echo "  -c, --cautious\n";
    echo "  -f, --force\n";
    echo "  --app-dir=<app-dir>\n";
    echo "  --backup-dir=<backup-dir>\n";
    echo "  -h, --help\n";
    exit(0);
}

$appDir = isset($options['app-dir']) ? $options['app-dir'] : '';
$backupDir = isset($options['backup-dir']) ? $options['backup-dir'] : '';
$backupOnly = isset($options['backup-only']) || isset($options['b']);
$cautious = isset($options['cautious']) || isset($options['c']);
$force = isset($options['force']) || isset($options['f']);
$shouldDeploy = !$backupOnly;

$updateCmd = isset($options['update-cmd']) ? 
    $options['update-cmd'] :
    implode(' && ', [
        'composer install -q --no-ansi --no-interaction --no-scripts --no-suggest --no-progress --prefer-dist',
        'php artisan migrate',
        'npm install',
        'npm run prod',
        'php artisan queue:restart',
        'nohup php artisan queue:work >> queue-work.log &',
    ]);

if (!file_exists($appDir)) {
    echo "App directory informed in --app-dir does not exist: $appDir\n";
    exit(1);
}

$appName = basename($appDir);

if (!file_exists($backupDir)) {
    if ($cautious) {
        echo "Backup directory informed in --backup-dir does not exist: $backupDir\n";
        exit(1);
    } else {
        @mkdir($backupDir, 0700, true);
    }
}

exec("cd '$appDir'; git fetch -u origin master 2>&1; [ $(git rev-parse HEAD) = $(git rev-parse @{u}) ] && echo 'Up to date' || echo 'Not up to date'", $output);
$status = implode('', $output);

if ($status == 'Up to date' && !$backupOnly) {
    echo "Up to date, nothing to do.\n";
    //exit(0);
}

$now = date('Y-m-d-h-i-s');
$source = $appDir . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'database.sqlite';

$destinationFolder = $backupDir . DIRECTORY_SEPARATOR . $appName;
$destination = $destinationFolder . DIRECTORY_SEPARATOR . $now . '_database.sqlite';

@mkdir($destinationFolder, 0700, true);

if (file_exists($source)) {
    if (!copy($source, $destination)) {
        echo "Could not copy database.sqlite from $source to $destination\n";
        exit(1);
    }

    echo "Copied \e[33m$destination\e[0m.\n";
}

if ($backupOnly) {
    exit(0);
}

$gitCmd = $force ? 'git reset --hard 2>&1; git pull 2>&1' : 'git merge 2>&1';
exec("cd '$appDir'; $gitCmd", $output, $result_code);

if ($result_code != 0) {
    echo "Could not merge: \e[91m$appDir\e[0m.\n";
    exit(3);
}

exec("cd '$appDir'; $updateCmd", $output, $result_code);

if ($result_code != 0) {
    echo "\e[91mFailed to run update command:\e[0m $updateCmd\n";
    exit(4);
}

echo "\e[32mFinished successfully!\e[0m.\n";
