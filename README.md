# hookdeploy

hookdeploy is a php script that deploys a bitbucket git repository to a web server.

### Install with composer

```sh
composer create-project avirdz/hookdeploy
```

Then remove .git dir.

### Requirements

 - You need ssh access to your bitbucket account.
 - If you enable composer, npm, bower or gulp, they must be accesible by www-data.
 - www-data needs write permissions to git_dir and branches paths.
 - www-data needs access to the configured private key.

### Resources
- SSH key generation: https://confluence.atlassian.com/bitbucket/configure-multiple-ssh-identities-for-gitbash-mac-osx-linux-271943168.html
- Add the deployment key: https://confluence.atlassian.com/bitbucket/use-deployment-keys-294486051.html
- Webhook management: https://confluence.atlassian.com/bitbucket/manage-webhooks-735643732.html
- Fix NPM permissions: https://docs.npmjs.com/getting-started/fixing-npm-permissions

### Global Config

### Tables

**Markdown Extra** has a special syntax for tables:

key      | Value     | Description
-------- | --------  | -------------
git      | (string) default: git |  git command (*)
composer    | (string) default: composer | composer command (*)
composer_home | (string) default: /usr/local/bin | composer home env var
npm | (string) default: npm | npm command (*)
gulp | (string) default: gulp | gulp command (*)
bower | (string) default: bower | bower command (*)
create_dirs | (bool) default: false| automatically create all necessary dirs, needs write permissions on different directories
hookdeploy_settings_dir | (string) default: /var/www/.hookdeploy_settings | composer, npm, and bower cache folders are created here.
http_test | (bool) default: false | if true you can make tests on your browser by accessing directly to the script, ip restriction must be disable
bg_command | (string) default: /dev/null 2>&1  | part of the command to run all other commands in background
bg_multiple | (string) default: /dev/null 2>&1 &  | run multiple commands in background
quiet | (string) default: -q | argument to run composer, npm and bower in silent mode
debug | (string) default: null | argument to run composer, npm and bower in debug mode, this argument is enabled via browser test

(*) avoid permissions errors by setting these commands with full path.

### Project Config

```
full-project-name => [ //config for this project, ex. myusername/myawesomeproject
    private_key => '~/.ssh/myPrivateKey' //full path for the ssh private key
    git_dir => '~/bitbucket_repos/myProject' //full path for the local clone repository
    remote_repository => 'git@bitbucket.org:myuser/myproject.git' //remote repository, ssh format
    branches => [ // branches to be deployed
        master => '/var/www/myproject-production' //ex. on this project master is used to deploy the production site
        develop => '/var/www/myproject-dev' //ex. on this project develop is used to deploy the development site
    ],
    run_composer => false //run the composer install command
    run_npm => false //run the npm install command
    run_bower => false //run the bower install command
    run_gulp => false, //run the gulp <task> command
    gulp_task => 'live' //gulp task to run
    bower_task => 'live_bower', //gulp task to run bower install, right now bower is running through gulp
]
```

### Notes

Test your private key and add bitbucket to known_hosts
```sh
ssh -i ~/.ssh/myPrivateKey -T git@bitbucket.org
```

If you want to test the script, run it emulating www-data, test with the provided payload.json file (you need to configure the full-name key by your project full name)
```sh
sudo -u www-data -H php hookdeploy.php test
```

Your custom payload.json
```sh
sudo -u www-data -H php hookdeploy.php test ~/mypayload.json
```

Run the script with your provided config, this runs your first project and the first branch configured on the $p var
```sh
sudo -u www-data -H php hookdeploy.php run
```

If you want to run a different project use this.
```sh
sudo -u www-data -H php hookdeploy.php run myuser/myotherproject
```

If you want to run with different branch.
```sh
sudo -u www-data -H php hookdeploy.php run myuser/myproject develop
```

- npm creates a .npm config dir on the user's home, I tested on /var/www since www-data has write permissions everything is ok, but in some cases this results on an EACCESS error by npm.

### Full Example

1. Install hookdeploy on the web server.
2. Configure projects, global config is ready but if you need something different then make your changes.
```php
$p = [
    'avirdz/shittyProject' => [
        'private_key' => '/var/www/.ssh/bitbucket_shittyProject',
        'git_dir' => '/var/www/bitbucket_repos/shittyProject',
        'remote_repository' => 'git@bitbucket.org:avirdz/shittyProject.git',
        'branches' => [
            'master' => '/var/www/html/shittyproject-production',
            'develop' => '/var/www/html/shittyproject-dev',
        ],
        'run_composer' => true,
        'run_npm' => true,
        'run_bower' => true
        'run_gulp' => true,
        'gulp_task' => 'live',
        'bower_task' => 'live_bower',
    ],
];
```
3. www-data home is /var/www
4. www-data has write permissions to /var/www
5. Run directly to check if it's working
```sh
sudo -u www-data -H php hookdeploy.php test
OR
sudo -u www-data -H php hookdeploy.php run
```
6. If everything is Ok, you have the git_dir created, and branches dirs created, also if no vendor, node_modules or bower_components exist they will be created, (only if config is true)


### Todos

 - Use bower without gulp

License
----

MIT
