<?php

define('DS', DIRECTORY_SEPARATOR);

$shortopts  = '';
$shortopts .= 'h::';
$shortopts .= 'b::';
$shortopts .= 'c::';
$shortopts .= 'f::';

$longopts  = array(
    "app-dir:",       
    "backup-dir:",
    "log-dir::",
    "init::",
    "help::",
    "backup-only::",
    "update-cmd::",
    "npm-run::",
    "cautious::",
    "force::",
);

$options = getopt($shortopts, $longopts);

if (count($options) == 0 || isset($options['h']) || isset($options['help'])) {
    echo "Usage:\n";
    echo "  php " . basename(__FILE__) . " [options]\n";
    echo "Options:\n";
    echo "  --app-dir=<app-dir>\n";
    echo "  --backup-dir=<backup-dir>\n";
    echo "  --log-dir=<log-dir>\n";    
    echo "  --init=<type>\n";    
    echo "  --npm-run=<string>\n";    
    echo "  -b, --backup-only\n";
    echo "  -c, --cautious\n";
    echo "  -f, --force\n";
    echo "  -h, --help\n";
    exit(0);
}

$appDir = isset($options['app-dir']) ? $options['app-dir'] : '';
$backupDir = isset($options['backup-dir']) ? $options['backup-dir'] : '';
$logDir = isset($options['log-dir']) ? $options['log-dir'] : '';
$init = isset($options['init']) ? $options['init'] : '';
$npmRun = isset($options['npm-run']) ? $options['npm-run'] : 'prod';
$backupOnly = isset($options['backup-only']) || isset($options['b']);
$cautious = isset($options['cautious']) || isset($options['c']);
$force = isset($options['force']) || isset($options['f']);

if ($logDir == '') {
    $logDir = $appDir;
}

if (!file_exists($appDir)) {
    echo "App directory informed in \e[33m--app-dir\e[0m does not exist: \e[33m$appDir\e[0m\n";
    exit(1);
}

$appName = basename($appDir);

if (!file_exists($backupDir)) {
    if ($cautious) {
        echo "Backup directory informed in \e[33m--backup-dir\e[0m does not exist: \e[33m$backupDir\e[0m\n";
        exit(1);
    } else {
        @mkdir($backupDir, 0700, true);
    }
}

if (!file_exists($logDir)) {
    if ($cautious) {
        echo "Log directory informed in \e[33m--log-dir\e[0m does not exist: \e[33m$logDir\e[0m\n";
        exit(1);
    } else {
        @mkdir($logDir, 0700, true);
    }
}

$updateCmd = isset($options['update-cmd']) ? 
    $options['update-cmd'] :
    implode(' && ', [
        'composer install -q --no-ansi --no-interaction --no-scripts --no-suggest --no-progress --prefer-dist',
        'php artisan migrate',
        'npm install',
        "npm run $npmRun",
        'php artisan queue:restart',
        "nohup php artisan queue:work >> $logDir/$appName-queue-work.log & echo $! > $logDir/$appName-queue-work.pid",
    ]);


exec("cd '$appDir'; git fetch -u origin master 2>&1; [ $(git rev-parse HEAD) = $(git rev-parse @{u}) ] && echo 'Up to date' || echo 'Not up to date'", $output);
$status = implode('', $output);

$now = date('Y-m-d-h-i-s');
$databaseFilePath = $appDir . DS . 'database' . DS . 'database.sqlite';

$destinationFolder = $backupDir . DS . $appName;
$databaseBackupFilePath = $destinationFolder . DS . $now . '_database.sqlite';

@mkdir($destinationFolder, 0700, true);

if ($init != '') {
    if ($init == 'laravel') {
        $cmds = [
            'cp .env.example .env',
            'composer install --no-interaction --no-cache',
            'php artisan key:generate',
            "touch $databaseFilePath",
            'php artisan migrate',
            'php artisan db:seed',
            'php artisan storage:link',
            'npm install',
            "npm run $npmRun",
        ];

        foreach ($cmds as $cmd) {
            $initLogFilePath = "$logDir/$appName-init.log";
            exec("cd '$appDir'; $cmd >> $initLogFilePath 2>&1", $output, $result_code);

            if ($result_code != 0) {
                echo "\e[91mFailed to run init command:\e[0m $cmd\n";
                echo "See log file $initLogFilePath\n.";
                exit(5);
            }
        }

        $cronFile = "/tmp/cronab-$appName";
        $cronCommend = "# Laravel scheduling for $appName ($appDir)";

        exec("crontab -l > $destinationFolder/$now-crontab.txt", $output, $result_code);
        file_put_contents($cronFile, "$cronCommend\n* * * * * cd '$appDir' && php artisan schedule:run >> /dev/null 2>&1\n");
        exec("crontab $cronFile", $output, $result_code);

        if ($result_code != 0) {
            echo 'Problem adding crontab. Please check.' . PHP_EOL;
        }

        echo "\e[32mSuccessful init of Laravel app:\e[0m $appName\n";
        exit(0);
    }
}

if ($status == 'Up to date' && !$backupOnly) {
    echo "Up to date, nothing to do.\n";
    exit(0);
}

if (file_exists($databaseFilePath)) {
    if (!copy($databaseFilePath, $databaseBackupFilePath)) {
        echo "Could not copy database.sqlite from $databaseFilePath to $databaseBackupFilePath\n";
        exit(1);
    }

    echo "New copy: \e[33m$databaseBackupFilePath\e[0m.\n";
}

if ($backupOnly) {
    exit(0);
}

$gitCmd = $force ? 'git reset --hard 2>&1; git pull 2>&1' : 'git merge 2>&1';
exec("cd '$appDir'; $gitCmd", $output, $result_code);

if ($result_code != 0) {
    echo "\e[91mCould not merge:\e[0m $appDir.\n";
    exit(3);
}

exec("cd '$appDir'; $updateCmd", $output, $result_code);

if ($result_code != 0) {
    echo "\e[91mFailed to run update command:\e[0m $updateCmd\n";
    exit(4);
}

echo "\e[32mFinished successfully!\e[0m.\n";
