<?php

#Test your private key and add bitbucket to known_hosts
#run ssh -i "yourkey" -T git@bitbucket.org
#you can test it emulating www-data user
#sudo -u www-data -H php hookdeploy.php
#make sure www-data has write permissions to its home dir, npm creates a .npm dir in user's home path
#npm permissions troubleshooting https://docs.npmjs.com/getting-started/fixing-npm-permissions
//@todo: bower is taking too much time

//global config
$g = [
    'git' => 'git',
    'composer' => 'composer',
    'npm' => 'npm',
    'gulp' => 'gulp',
    //'bower' => 'bower' //not, bower is running through gulp
];

//project config
$p = [
    'full-project-name' => [
        'private_key' => '~/.ssh/myPrivateKey',
        'git_dir' => '~/bitbucket_repos/myProject',
        'remote_repository' => 'git@bitbucket.org:myuser/myproject.git',
        'branches' => [
            'master' => '/var/www/myproject-production',
            'develop' => '/var/www/myproject-dev',
        ],
        'run_composer' => false,
        'run_npm' => false,
        'run_bower' => false,
        'run_gulp' => false, //always run gulp in order to propagate the public files
        'gulp_task' => 'live',
        'bower_task' => 'live_bower', //when bower is modified run a combination bower install and gulp live task
    ],
];

$payload = json_decode(file_get_contents('php://input'));

if(empty($payload)) {
    die('No payload');
}

if(empty($payload->push->changes)) {
    die('No changes');
}

if($payload->push->changes[0]->new->type != 'branch') {
    die('No changes on branches');
}

$project_name = $payload->push->changes[0]->new->repository->full_name;
$branch_name = $payload->push->changes[0]->new->name;
if(!isset($p[$project_name])) {
    die('No config for this project: ' . $project_name);
}

if(!isset($p[$project_name]['branches'][$branch_name])) {
    die('No config for this branch: ' . $branch_name);
}

$git_dir_base = pathinfo($p[$project_name]['git_dir'], PATHINFO_DIRNAME);
$work_tree_base = pathinfo($p[$project_name]['branches'][$branch_name], PATHINFO_DIRNAME);

if(!is_writable($git_dir_base)) {
    die('Make sure www-data has write permission on: ' . $git_dir_base);
}

if(!is_writable($work_tree_base)) {
    die('Make sure www-data has write permission on: ' . $work_tree_base);
}

if(!is_dir($p[$project_name]['branches'][$branch_name])) {
    if(!mkdir($p[$project_name]['branches'][$branch_name])) {
        die('Cannot create dir: ' . $p[$project_name]['branches'][$branch_name] . ', please check your dir paths.');
    }
}

//use an specific private key
//make sure you have git v2.3.0 or newer
putenv("GIT_SSH_COMMAND=ssh -i {$p[$project_name]['private_key']}");

$o = [];

//clone if git dir does not exist
if(!is_dir($p[$project_name]['git_dir'])) {
    $o[] = shell_exec("{$g['git']} clone {$p[$project_name]['remote_repository']} {$p[$project_name]['git_dir']} 2>&1");
}

if(is_dir($p[$project_name]['git_dir'])) {
    putenv("GIT_DIR={$p[$project_name]['git_dir']}/.git");
    putenv("GIT_WORK_TREE={$p[$project_name]['branches'][$branch_name]}");

    $o[] = shell_exec("{$g['git']} fetch --all 2>&1");
    $o[] = shell_exec("{$g['git']} checkout -f origin/{$branch_name} 2>&1");

    //compute changes if any of the post commands need to be executed
    if($p[$project_name]['run_composer'] || $p[$project_name]['run_npm'] || $p[$project_name]['run_gulp'] || $p[$project_name]['run_bower']) {
        $changes = shell_exec("{$g['git']} show --name-only --diff-filter=MA --pretty=format:%b HEAD 2>&1");
        $changed_files = explode("\n", trim($changes));
        //it might be truncated by bitbucket webhook
        $o[] = 'changed files: ' . PHP_EOL . trim($changes);
    }

    //composer
    $run_composer = false;
    if($p[$project_name]['run_composer']) {
        //if vendor dir does not exist, run composer install
        if(!is_dir($p[$project_name]['branches'][$branch_name] . '/vendor')) {
            $run_composer = true;
        } elseif (in_array('composer.json', $changed_files)) {
            //changes on composer, run composer install
            $run_composer = true;
        }


        if($run_composer) {
            //needs to run on background
            $o[] = "running background command: (cd {$p[$project_name]['branches'][$branch_name]} && {$g['composer']} install -q --no-progress)";
            shell_exec("(cd \"{$p[$project_name]['branches'][$branch_name]}\" && {$g['composer']} install -q --no-progress) > /dev/null 2>&1 &");
        }
    }

    //nmp
    $run_npm = false;
    if($p[$project_name]['run_npm']) {
        //if node_modules does not exist, run npm install
        if(!is_dir($p[$project_name]['branches'][$branch_name] . '/node_modules')) {
            $run_npm = true;
        } elseif (in_array('package.json', $changed_files)) {
            //changes on npm, run npm install
            $run_npm = true;
        }
    }

    //bower
    $run_bower = false;
    if($p[$project_name]['run_bower']) {
        //if bower_components does not exist, run bower install
        if(!is_dir($p[$project_name]['branches'][$branch_name] . '/bower_components')) {
            $run_bower= true;
        } elseif (in_array('bower.json', $changed_files)) {
            //changes on npm, run npm install
            $run_bower = true;
        }
    }

    //gulp
    //does not need to check changes on gulp.js
    //run always in order to propagate public files
    $run_gulp = $p[$project_name]['run_gulp'];

    //check combinations of changes
    //1. npm install, 2. bower install, 3. gulp [task]

    if($run_npm && $run_bower) {
        //needs to run on background
        $o[] = "running background command: (cd {$p[$project_name]['branches'][$branch_name]} && {$g['npm']} install && {$g['gulp']} {$p[$project_name]['bower_task']})";
        shell_exec("(cd \"{$p[$project_name]['branches'][$branch_name]}\" && {$g['npm']} install && {$g['gulp']} {$p[$project_name]['bower_task']}) > /dev/null 2>&1 &");
    } else {
        if($run_npm) {
            $o[] = "running background command: (cd {$p[$project_name]['branches'][$branch_name]} && {$g['npm']} install && {$g['gulp']} {$p[$project_name]['gulp_task']})";
            shell_exec("(cd \"{$p[$project_name]['branches'][$branch_name]}\" && {$g['npm']} install && {$g['gulp']} {$p[$project_name]['gulp_task']}) > /dev/null 2>&1 &");
        } elseif($run_bower) {
            $o[] = "running background command: (cd {$p[$project_name]['branches'][$branch_name]} && {$g['gulp']} {$p[$project_name]['bower_task']})";
            shell_exec("(cd \"{$p[$project_name]['branches'][$branch_name]}\" && {$g['gulp']} {$p[$project_name]['bower_task']}) > /dev/null 2>&1 &");
        } elseif($run_gulp) {
            //running in background it might be delayed and bitbucket has a timeout limit
            $o[] = "running background command: (cd {$p[$project_name]['branches'][$branch_name]} && {$g['gulp']} {$p[$project_name]['gulp_task']})";
            shell_exec("(cd \"{$p[$project_name]['branches'][$branch_name]}\" && {$g['gulp']} {$p[$project_name]['gulp_task']}) > /dev/null 2>&1 &");
        }
    }
}

echo implode(PHP_EOL, $o) . PHP_EOL;