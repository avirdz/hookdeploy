<?php

$config_file = __DIR__ . '/config.php';

if(file_exists($config_file)) {
    include($config_file);
} else {
    die('config.php file does not exist');
}

$run_custom = false;
$run_composer = false;
$run_npm = false;
$run_bower = false;
$o = [];

if(isset($_GET['run']) && $g['http_test']) {
    $payload = json_decode(file_get_contents(__DIR__ . '/payload.json'));
    if(isset($_GET['r'])) {
        $payload->repository->full_name = $_GET['r'];
    } else {
        $projects = array_keys($p);
        $payload->repository->full_name = $projects[0];
    }

    if(isset($_GET['b'])) {
        $payload->push->changes[0]->new->name = $_GET['b'];
    } else {
        if(isset($p[$payload->repository->full_name])) {
            $branches_list = array_keys($p[$payload->repository->full_name]['branches']);
            $payload->push->changes[0]->new->name = $branches_list[0];
        }
    }
    $run_custom = true;

    $run_composer = strpos($_GET['run'], 'composer') === false ? false : true;
    $run_npm = strpos($_GET['run'], 'npm') === false ? false : true;
    $run_bower = strpos($_GET['run'], 'bower') === false ? false : true;
    if(isset($_GET['bg'])) {
        $g['bg_command'] = ' 2>&1';
        $g['quiet'] = null;
        $g['debug'] = ' --verbose';
    }
} else {
    //bower continuous integration
    putenv("CI=true");
}

if(!$run_custom) {
    $payload = json_decode(file_get_contents('php://input'));
}

if(empty($payload)) {
    die('No payload');
}

if(empty($payload->push->changes)) {
    die('No changes');
}

if(empty($payload->push->changes[0]->new) || $payload->push->changes[0]->new->type != 'branch') {
    die('No changes on branches');
}

$project_name = $payload->repository->full_name;
$branch_name = $payload->push->changes[0]->new->name;
if(!isset($p[$project_name])) {
    die('No config for this project: ' . $project_name);
}

$working_dir = $p[$project_name]['branches'][$branch_name];
if($p[$project_name]['releases']['deploy']) {
    $releaseCheck = preg_match($p[$project_name]['releases']['name_regex'], $branch_name);

    if(!$releaseCheck && !isset($p[$project_name]['branches'][$branch_name])) {
        die('No config for this branch: ' . $branch_name);
    } else if($releaseCheck) {
        $working_dir = $p[$project_name]['releases']['path'];
    } else if(!isset($p[$project_name]['branches'][$branch_name])) {
        die('No config for this branch: ' . $branch_name);
    }

} else if(!isset($p[$project_name]['branches'][$branch_name])) {
    die('No config for this branch: ' . $branch_name);
}

$o[] = 'working-dir: ' . $working_dir;

//check necessary directories and permissions
if($g['create_dirs']) {
    $git_dir_base = pathinfo($p[$project_name]['git_dir'], PATHINFO_DIRNAME);
    $work_tree_base = pathinfo($working_dir, PATHINFO_DIRNAME);

    if (!is_writable($git_dir_base)) {
        die('Make sure www-data has write permission on: ' . $git_dir_base);
    }

    if(!is_writable($work_tree_base)) {
        die('Make sure www-data has write permission on: ' . $work_tree_base);
    }

    if (!is_dir($working_dir)) {
        if (!mkdir($working_dir)) {
            die('Cannot create dir: ' . $working_dir . ', please check your dir paths.');
        }
    }


    if(!empty($g['hookdeploy_settings_dir'])) {
        $settings_dir = pathinfo($g['hookdeploy_settings_dir'], PATHINFO_DIRNAME);

        if (!is_writable($settings_dir)) {
            die('Make sure www-data has write permission on: ' . $settings_dir);
        }

        if(!is_dir($g['hookdeploy_settings_dir'])) {
            if(!mkdir($g['hookdeploy_settings_dir'])) {
                die('Cannot create dir: ' . $g['hookdeploy_settings_dir'] . ', please check your dir paths.');
            }
        }
    }
} else {
    if(!is_dir($working_dir)) {
        die("the directory for branch {$branch_name} does not exist: " . $working_dir);
    }

    if(!is_writable($working_dir)) {
        die('Make sure www-data has write permission on: ' . $working_dir);
    }
}

if(!empty($g['hookdeploy_settings_dir'])) {
    if(!is_dir($g['hookdeploy_settings_dir'])) {
        die('hookdeploy_settings_dir does not exist: ' . $g['hookdeploy_settings_dir']);
    }

    putenv("COMPOSER_CACHE_DIR={$g['hookdeploy_settings_dir']}/.composer_cache");
    putenv("NPM_CONFIG_CACHE={$g['hookdeploy_settings_dir']}/.npm_cache");
    putenv("bower_storage__packages={$g['hookdeploy_settings_dir']}/.bower_packages");
    putenv("bower_storage__registry={$g['hookdeploy_settings_dir']}/.bower_registry");
    putenv("bower_storage__links={$g['hookdeploy_settings_dir']}/.bower_links");
}


//use an specific private key
//make sure you have git v2.3.0 or newer
putenv("GIT_SSH_COMMAND=ssh -o StrictHostKeyChecking=no -i {$p[$project_name]['private_key']}");

//clone if git dir does not exist
if(!is_dir($p[$project_name]['git_dir'])) {
    $o[] = shell_exec("{$g['git']} clone {$p[$project_name]['remote_repository']} {$p[$project_name]['git_dir']} 2>&1");
}

if(is_dir($p[$project_name]['git_dir'])) {
    putenv("GIT_DIR={$p[$project_name]['git_dir']}/.git");
    putenv("GIT_WORK_TREE={$working_dir}");

    $o[] = shell_exec("{$g['git']} fetch --all 2>&1");
    $o[] = shell_exec("{$g['git']} checkout -f origin/{$branch_name} 2>&1");

    $changed_files = [];
    //compute changes if any of the post commands need to be executed
    if($p[$project_name]['run_composer'] || $p[$project_name]['run_npm'] || $p[$project_name]['run_gulp'] || $p[$project_name]['run_bower']) {
        if(!empty($payload->push->changes[0]->old)) {
            $old_commit_hash = $payload->push->changes[0]->old->target->hash;
            $new_commit_hash = $payload->push->changes[0]->new->target->hash;

            if(!empty($old_commit_hash) && !empty($new_commit_hash)) {
                //Compare the version between the old commit and the new commit.
                $changes = shell_exec("{$g['git']} --no-pager diff --diff-filter=M --name-only {$old_commit_hash} {$new_commit_hash} 2>&1");
                $changed_files = explode("\n", trim($changes));
                //it might be truncated by bitbucket webhook
                $o[] = 'changed files: ' . PHP_EOL . trim($changes);
            }
        } else if($releaseCheck) {
            //this is the first time a release is pushed, run all the commands
            $run_composer = true;
            $run_npm = true;
            $run_bower = true;
        }
    }

    //composer
    if($p[$project_name]['run_composer']) {
        //if vendor dir does not exist, run composer install
        if(!is_dir($working_dir . '/vendor')) {
            $run_composer = true;
        } elseif (in_array('composer.json', $changed_files) || in_array('composer.lock', $changed_files)) {
            //changes on composer, run composer install
            $run_composer = true;
        }


        if($run_composer) {
            //set needed env var for composer
            putenv("COMPOSER_HOME={$g['composer_home']}");

            //needs to run on background
            $o[] = "running background command: {$g['composer']} --working-dir=\"{$working_dir}\" install --no-dev{$g['quiet']}{$g['debug']}";
            $o[] = shell_exec("{$g['composer']} --working-dir=\"{$working_dir}\" install --no-dev{$g['quiet']}{$g['debug']}{$g['bg_command']}");
        }
    }

    //nmp
    if($p[$project_name]['run_npm']) {
        //if node_modules does not exist, run npm install
        if(!is_dir($working_dir . '/node_modules')) {
            $run_npm = true;
        } elseif (in_array('package.json', $changed_files)) {
            //changes on npm, run npm install
            $run_npm = true;
        }
    }

    //bower

    if($p[$project_name]['run_bower']) {
        //if bower_components does not exist, run bower install
        if(!is_dir($working_dir . '/bower_components')) {
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

    //check for gulp commands
    $run_this_gulp_task = null;
    if($run_gulp && !empty($p[$project_name]['valid_gulp_tasks'])) {
        $last_commit = shell_exec("{$g['git']} log -1 --pretty=%B 2>&1");
        $matches = [];
        if(preg_match('/\#gulp ([^\#]*)\#/im', $last_commit, $matches)) {
            //eval the first one
            if(isset($matches[1]) && in_array($matches[1], $p[$project_name]['valid_gulp_tasks'])) {
                $run_this_gulp_task = "{$g['gulp']} {$matches[1]} && ";
            }
        }
    }

    //check combinations of changes
    //1. npm install, 2. bower install, 3. gulp [task]

    //remove git env vars
    putenv('GIT_WORK_TREE');
    putenv('GIT_DIR');
    putenv('GIT_SSH_COMMAND');

    //setting home for bower
    if(!empty($g['hookdeploy_settings_dir'])) {
        putenv("HOME={$g['hookdeploy_settings_dir']}/.bower_home");
    }

    //cd to the working dir
    chdir($working_dir);


    if($run_npm && $run_bower && $run_gulp) {
        $o[] = "running background command: ({$g['npm']} install{$g['quiet']}{$g['debug']} && {$g['bower']} install{$g['quiet']}{$g['debug']} && {$run_this_gulp_task}{$g['gulp']} {$p[$project_name]['gulp_task']})";
        $o[] = shell_exec("({$g['npm']} install{$g['quiet']}{$g['debug']} && {$g['bower']} install{$g['quiet']}{$g['debug']} && {$run_this_gulp_task}{$g['gulp']} {$p[$project_name]['gulp_task']}){$g['bg_command']}");
    } else {
        if($run_npm) {
            if($run_gulp) {
                $o[] = "running background command: ({$g['npm']} install{$g['quiet']}{$g['debug']} && {$run_this_gulp_task}{$g['gulp']} {$p[$project_name]['gulp_task']})";
                $o[] = shell_exec("({$g['npm']} install{$g['quiet']}{$g['debug']} && {$g['gulp']} {$run_this_gulp_task}{$p[$project_name]['gulp_task']}){$g['bg_command']}");
            } else {
                $o[] = "running background command: {$g['npm']} install{$g['quiet']}{$g['debug']}";
                $o[] = shell_exec("{$g['npm']} install{$g['quiet']}{$g['debug']}{$g['bg_command']}");
            }
        } elseif($run_bower) {
            if($run_gulp) {
                $o[] = "running background command: ({$g['bower']} install{$g['quiet']}{$g['debug']} && {$run_this_gulp_task}{$g['gulp']} {$p[$project_name]['gulp_task']})";
                $o[] = shell_exec("({$g['bower']} install{$g['quiet']}{$g['debug']} && {$run_this_gulp_task}{$g['gulp']} {$p[$project_name]['gulp_task']}){$g['bg_command']}");
            } else {
                $o[] = "running background command: {$g['bower']} install{$g['quiet']}{$g['debug']}";
                $o[] = shell_exec("{$g['bower']} install{$g['quiet']}{$g['debug']}{$g['bg_command']}");
            }
        } elseif($run_gulp) {
            if(!empty($run_this_gulp_task)) {
                $o[] = "running background command: ({$run_this_gulp_task}{$g['gulp']} {$p[$project_name]['gulp_task']})";
                $o[] = shell_exec("({$run_this_gulp_task}{$g['gulp']} {$p[$project_name]['gulp_task']}){$g['bg_command']}");
            } else {
                $o[] = "running background command: {$g['gulp']} {$p[$project_name]['gulp_task']})";
                $o[] = shell_exec("{$g['gulp']} {$p[$project_name]['gulp_task']}{$g['bg_command']}");
            }
        }
    }
}

if(isset($_GET['run']) && $g['http_test']) {
    foreach($o as $l) {
        if(!empty($l)) {
            echo '<pre>' . $l . '</pre>';
        }
    }
} else {
    echo implode(PHP_EOL, $o) . PHP_EOL;
}
