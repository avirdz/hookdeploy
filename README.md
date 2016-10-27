# hookdeploy

hookdeploy is a php deployment script for bitbucket git repositories. It also has a basic integration with composer, npm, bower and gulp.

### Install with composer

```sh
composer create-project avirdz/hookdeploy
```

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

Add a new file config.php, copy the content of the file config.example.php and make your changes.

Key      | Value     | Description
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
bg_command | (string) default: /dev/null 2>&1 &  | part of the command to run all other commands in background
quiet | (string) default: -q | argument to run composer, npm and bower in silent mode
debug | (string) default: null | argument to run composer, npm and bower in debug mode, this argument is enabled via browser test

(*) avoid permissions errors by setting these commands with full path.

### Project Config

Key      | Value     | Description
-------- | --------  | -------------
full-project-name | (array) | replace the key name by your full project name |
- private_key | (string) |  full path for ssh private key
- git_dir    | (string)  | full path for the local clone repository
- remote_repository | (string) | remote repository path ssh format
- branches| (array) | branches to be deployed,
|||`'branch_name' => 'deployment path'` common environment branches, like master (production) , develop (development)
- releases | (array) | releases to be deployed,
|||`'deploy' => false,  ` deploy releases
|||`'name_regex' => '/^release\/v?[0-9]+\.[0-9]+(?:\.[0-9]+)?$/i', `  regex for releases name
|||`'path' => '/var/www/myproject-latest'` path to deploy the latest release
- run_composer | (bool) default: false | run composer install command
- run_npm | (bool) default: false | run npm install command
- run_bower | (bool) default: false | run bower install command
- run_gulp  | (bool) default: false | run gulp task command
- gulp_task  | (string) default: live | name of the task to run
- valid_gulp_tasks | (array) | valid gulp tasks to run via commit message 

### Run gulp tasks via commit message
You can run gulp tasks by a commit message, just add to your last commit message the following format:
```sh
#gulp <task>#
```
Replace &lt;task&gt; for a valid task on the project config valid_gulp_tasks key.

Commit example:
```sh
Removed some unused files #gulp clear:all#
```

### Testing

Test your private key and add bitbucket to known_hosts
```sh
sudo -Hu www-data ssh -i ~/.ssh/myPrivateKey -T git@bitbucket.org
```
#### Testing via browser
This is the best way to test your setup.

Configure hookdeploy to run script via browser setting the global config "http_test" key to true.
Then temporally disable ip restriction from the .htaccess
Enter the URL to hookdeploy script on the browser, use the following GET parameters:

Key      | Value     | Description
-------- | --------  | -------------
run | empty\|composer\|npm\|bower\|gulp |   this param is required to be set in order to run the http test, you can set any of the other commands to test, for example: composer (this run the composer install command), you can separate by a comma or any other separator to run multiple commands, example: composer,npm|
bg | any value | this param is required if you want to disable silent mode on commands and activate debug output to get more information, commands run normally (background and silent mode) if this param is not present
r | full project name | project to test, full project name from the \$p config, slash must be encode with "%2F", the script takes the first project from your \$p config if this param is not present
b | branch name | branch to test, the script takes the first branch from your project config if this param is not present

##### Sample test URL

```sh
http://my-host/hookdeploy.php?run=composer&bg=1&r=myuser%2Fmyproject&b=develop
```

License
----

MIT