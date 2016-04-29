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
bg_command | (string) default: /dev/null 2>&1  | part of the command to run all other commands in background
bg_multiple | (string) default: /dev/null 2>&1 &  | run multiple commands in background
quiet | (string) default: -q | argument to run composer, npm and bower in silent mode
debug | (string) default: null | argument to run composer, npm and bower in debug mode, this argument is enabled via browser test

(*) avoid permissions errors by setting these commands with full path.

### Project Config

Key      | Value     | Description
-------- | --------  | -------------
full-project-name | (array) | replace the key name by your full project name |
- private_key | (string) |  full path foe ssh private key
- git_dir    | (string)  | full path for the local clone repository
- remote_repository | (string) | remote repository path ssh format
- branches| (array) | branches to be deployed, ['branch_name' => 'deployment path']
- run_composer | (bool) default: false | run composer install command
- run_npm | (bool) default: false | run npm install command
- run_bower | (bool) default: false | run bower install command
- run_gulp  | (bool) default: false | run gulp task command
- gulp_task  | (string) default: live | name of the task to run


### Testing

Test your private key and add bitbucket to known_hosts
```sh
ssh -i ~/.ssh/myPrivateKey -T git@bitbucket.org
```
#### Testing via browser
This is the best way to test your setup.

Configure hookdeploy to run script via browser setting the global config "http_test" key to true.
Then temporally disable ip restriction from the .htaccess
Enter the URL to hookdeploy script on the browser, use the following GET parameters:

Key      | Value     | Description
-------- | --------  | -------------
run | empty\|composer\|npm\|bower\|gulp |   this param is required to be set in order to run the http test, you can set any of the other commands to test, for example: composer (this run the composer install command), you can separate by a comma or any other separator to run multiple commands, example: composer,npm|
bg | any value | this param is required is you want to disable silent mode on commands, and activate debug output to get more information, if not present commands run normally (background and silent mode)
r | full project name | project to test, full project name from the \$p config, slash must be encode with "%2F", is this value is not present the script takes the first project from your \$p config
b | branch name | branch to test, if not present the script takes the first branch from your project config

##### Sample test URL
```sh
http://my-host/hookdeploy.php?run=composer&bg=1&r=myuser%2Fmyproject&b=develop
```

I recommend to  test your setup via browser because you can check if something is wrong.

####Testing by command line

If you want to test the script, run it emulating www-data, test with the provided payload.json file (you need to configure the full-name key by your project full name)
```sh
sudo -Hu www-data php hookdeploy.php test
```

Your custom payload.json
```sh
sudo -Hu www-data php hookdeploy.php test ~/mypayload.json
```

Run the script with your provided config, this runs your first project and the first branch configured on the $p var
```sh
sudo -Hu www-data php hookdeploy.php run
```

If you want to run a different project use this.
```sh
sudo -Hu www-data php hookdeploy.php run myuser/myotherproject
```

If you want to run with different branch.
```sh
sudo -Hu www-data php hookdeploy.php run myuser/myproject develop
```

License
----

MIT
