<?php

//config vars
$c = [
    'private_key' => '/home/myuser/.ssh/privateKey',
    'git_work_tree' => '/var/www/html/productionSite',
    'git_dir' => '/home/myuser/repos/myproject/.git',
    'remote_repository' => 'git@bitbucket.org:myuser/myproject.git',
];

//use an specific private key
putenv("GIT_SSH_COMMAND=ssh -i {$c['private_key']}");

$o = [];

if(is_dir($c['git_dir'])) {
    if(!is_dir($c['git_work_tree'])) {
        mkdir($c['git_work_tree']);
    }

    putenv("GIT_DIR={$c['git_dir']}");
    putenv("GIT_WORK_TREE={$c['git_work_tree']}");

    $o[] = shell_exec("git fetch --all 2>&1");
    $o[] = shell_exec('git checkout -f origin/master 2>&1');
} else {
    $o[] = shell_exec("git clone {$c['remote_repository']} {$c['git_dir']} 2>&1");
}

print_r($o);