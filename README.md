# hookdeploy

hookdeploy is a php script that deploys a bitbucket git repository to a web server.

### Install with composer

This creates a hookdeploy directory in the current dir.
```sh
composer create-project avirdz/hookdeploy=dev-master
```

This installs the files in the current dir.
```sh
composer create-project avirdz/hookdeploy=dev-master .
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

```
git => 'git' //git command, you can use the full path
composer => 'composer' //composer command, you can use the full path
composer_home => '/usr/local/bin/' //composer home environment
npm => 'npm' //npm command, you can use the full path
gulp => 'gulp' //gulp command, you can use the full path
```

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

If you want to test the script, run it emulating www-data
```sh
sudo -u www-data -H php hookdeploy.php
```
You need a payload.json to run the test, grab it from bitbucket requests save it on payload.json and replace this line
```php
$payload = json_decode(file_get_contents('php://input'));
```
by this one
```php
$payload = json_decode('full_path_to_json_payload');
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
5. Run directly to check if it's working, remember to add your payload.json
```sh
sudo -u www-data -H php hookdeploy.php
```
6. If everything is Ok, you have the git_dir created, and branches dirs created, also if no vendor, node_modules or bower_components exist they will be created, (only if config is true)


### Todos

 - Use bower without gulp

License
----

MIT