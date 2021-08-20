<?php

define('DS', DIRECTORY_SEPARATOR);

function exec_dry($cmd, &$output, &$result_code, $dry_run = false) {
    if ($dry_run) {
        echo "\e[33m[fake-run]\e[0m $cmd\n";
        $output = ['fake', 'run'];
        $result_code = 0;
        return;
    }
    exec($cmd, $output, $result_code);
}

$shortopts  = '';
$shortopts .= 'h::';
$shortopts .= 'b::';
$shortopts .= 'd::';
$shortopts .= 'c::';
$shortopts .= 'f::';
$shortopts .= 'r::';
$shortopts .= 'v::';

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
    "reset::",
    "dry-run::",
    "version::",
);

$options = getopt($shortopts, $longopts);

if (count($options) == 0 || isset($options['h']) || isset($options['help'])) {
    echo "Usage:\n";
    echo "  php " . basename(__FILE__) . " [options]\n\n";
    echo "Options:\n";
    echo "  --app-dir=\e[33m<app-dir>\e[0m         Path to app folder that will be deployed.\n\n";
    echo "  --backup-dir=\e[33m<backup-dir>\e[0m   Path to a folder that will be used as a storage\n";
    echo "                              for backup files, e.g. database before migration.\n\n";
    echo "  --log-dir=\e[33m<log-dir>\e[0m         Path to a folder that will store log files created\n";    
    echo "                              during executions.\n\n";
    echo "  --init=\e[33m<type>\e[0m               Initialize the app for first use, i.e. create db, run\n";
    echo "                              migration, etc. The only available type is 'laravel'\n";
    echo "                              for now.\n\n";
    echo "  --npm-run=\e[33m<string>\e[0m          Value to be used in 'npm run xxx' commands. Default is 'prod',\n";    
    echo "                              but you can use something like 'dev' to yield 'npm run dev'.\n\n";
    echo "  -b, --backup-only           Only backup operations will be performed, but no update/fetch\n";
    echo "                              of new code (or migrations).\n\n";
    echo "  -c, --cautious              Make the script abort if any of the informed\n";
    echo "                              backup/log folder does not exist.\n\n";
    echo "  -f, --force                 Force an app update, even if no new code is pulled.";    
    echo "  -r, --reset                 Eliminate non-tracked files within the local git repo of the app\n";
    echo "                              before performing any action, i.e. git reset --hard. The default behaviour\n";
    echo "                              is to leave non-tracked files alone, i.e. git pull.\n\n";
    echo "  -d, --dry-run               Simulates a deploy, but no real operation is performed (great for testing).\n";    
    echo "  -v, --version               Display application version.\n";
    echo "  -h, --help                  Show this help.\n";
    exit(0);
}

if (isset($options['v']) || isset($options['version'])) {
    echo "\e[32mdeployer\e[0m \e[33mv1.0.0\e[0m - cli to deploy PHP apps\n";
    echo "by Fernando Bevilacqua <dovyski@gmail.com>\n";
    echo "See https://github.com/Dovyski/deployer\n";
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
$reset = isset($options['reset']) || isset($options['r']);
$dry = isset($options['dry-run']) || isset($options['dry']) || isset($options['d']);

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

if ($init != '' && $init != 'laravel') {
    echo "Informed \e[91m$init\e[0m is invalid for \e[33m--init\e[0m.\n";
    exit(1);
}

$queueCmd = "nohup php artisan queue:work >> $logDir/$appName-queue-work.log & echo $! > $logDir/$appName-queue-work.pid";
$updateCmd = isset($options['update-cmd']) ? 
    $options['update-cmd'] :
    implode(' && ', [
        'composer install --no-ansi --no-interaction --no-scripts --prefer-dist',
        'php artisan migrate',
        'npm install',
        "npm run $npmRun",
        'php artisan queue:restart',
        $queueCmd,
    ]);


exec_dry("cd '$appDir'; git fetch -u origin master 2>&1; [ $(git rev-parse HEAD) = $(git rev-parse @{u}) ] && echo 'Up to date' || echo 'Needs update'", $output, $result_code, $dry);
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
            exec_dry("cd '$appDir'; $cmd >> $initLogFilePath 2>&1", $output, $result_code, $dry);

            if ($result_code != 0) {
                echo "\e[91mFailed to run init command:\e[0m $cmd\n";
                echo "See log: \e[33m$initLogFilePath\e[0m\n";
                exit(5);
            }
        }

        $cronFile = "/tmp/cronab-$appName";
        $cronCommend = "# Laravel scheduling for $appName ($appDir)";

        exec_dry("crontab -l > $destinationFolder/$now-crontab.txt", $output, $result_code, $dry);
        $contentExistingCrontab = file_get_contents("$destinationFolder/$now-crontab.txt");
        file_put_contents($cronFile, $contentExistingCrontab . "\n\n$cronCommend\n* * * * * cd '$appDir' && php artisan schedule:run >> /dev/null 2>&1\n");
        exec_dry("crontab $cronFile", $output, $result_code, $dry);

        if ($result_code != 0) {
            echo 'Problem adding crontab. Please check.' . PHP_EOL;
        }

        echo "\e[32mSuccessful init of Laravel app:\e[0m $appName\n";
        exit(0);
    }
}

if (stripos($status, 'Up to date') !== false && !$backupOnly && !$force) {
    echo "\e[32mAll good!\e[0m App is up to date.\n";
    exit(0);
}

if (file_exists($databaseFilePath)) {
    if (!copy($databaseFilePath, $databaseBackupFilePath)) {
        echo "Could not copy database.sqlite from $databaseFilePath to $databaseBackupFilePath\n";
        exit(1);
    }

    echo "New backup: \e[33m$databaseBackupFilePath\e[0m.\n";
}

if ($backupOnly) {
    exit(0);
}

$gitCmd = $reset ? 'git reset --hard 2>&1; git pull 2>&1' : 'git merge 2>&1';
exec_dry("cd '$appDir'; $gitCmd", $output, $result_code, $dry);

if ($result_code != 0) {
    echo "\e[91mCould not merge:\e[0m $appDir.\n";
    exit(3);
}

exec_dry("cd '$appDir'; $updateCmd", $output, $result_code, $dry);

if ($result_code != 0) {
    echo "\e[91mFailed to run update command:\e[0m $updateCmd\n";
    exit(4);
}

echo "\e[32mFinished successfully!\e[0m.\n";
