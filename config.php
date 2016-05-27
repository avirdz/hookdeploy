<?php

//global config
$g = [
    'git' => 'git',
    'composer' => 'composer',
    'composer_home' => '/usr/local/bin/', //composer home env var
    'npm' => 'npm',
    'gulp' => 'gulp',
    'bower' => 'bower',
    'create_dirs' => false, //true: create necessary dirs with mkdir, false you need to create them manually.
    'hookdeploy_settings_dir' => '/var/www/.hookdeploy_settings',
    'http_test' => false, //can make a test request from the browser, ip restriction must be disabled
    'bg_command' => ' > /dev/null 2>&1 &',
    'quiet' => ' -q',
    'debug' => null,
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
        'releases' => [
            'deploy' => false,
            'name_regex' => '/^release\/v?[0-9]+\.[0-9]+(?:\.[0-9]+)?$/i',
            'path' => '/var/www/myproject-latest'
        ],
        'run_composer' => false,
        'run_npm' => false,
        'run_bower' => false,
        'run_gulp' => false,
        'gulp_task' => 'live',
        'valid_gulp_tasks' => [],
    ],
];
