# ansible-flarum

This playbook will install a LEMP environment + Flarum on an Ubuntu 22.04 machine.
A virtualhost will be created with the options specified in the `vars/default.yml` variable file.

## Settings
- `php_version`: the php version. tested with php `8.3`.
- `mysql_root_password`: the password for the MySQL root account.
- `mysql_flarum_user`: the user created for flarum database in mysql.
- `mysql_flarum_password`: the password for flarum database user in mysql.
- `mysql_flarum_database`: the flarum database name in mysql.
- `mysql_datadir_changed`: optional. boolean flag for mysql custom data directory. example: `false`
- `mysql_datadir`: optional. the mysql data directory. default: `/var/lib/mysql`.
- `mysql_innodb_buffer_pool_size`: mysql.cnf `innodb_buffer_pool_size` property. set to 80% of your server ram. example: `12G`
- `mysql_innodb_log_file_size`: mysql.cnf `innodb_log_file_size` property. set to 80% of your server ram. example: `12G`
- `mysql_innodb_log_buffer_size`: mysql.cnf `innodb_log_buffer_size` property. example: `128M`
- `nginx_http_host`: your domain name. example: `yoursite`
- `nginx_http_conf_name`: the name of the configuration file that will be created within nginx. example: `yoursite.conf`
- `nginx_http_port`: HTTP port, default is `80`.
- `nginx_healthcheck_root`: healthcheck files root folder, refer to `files/health_check.php.j2`. example: `/var/www/html/health-check`
- `nginx_flarum_root`: flarum project root folder for nginx. it should be the folder in which `public` folder lies. it recommended to use [deployer](https://deployer.org/) for deploying flarum. use [deploy.php](deploy.php) file as recipe. example: `/var/www/html/flarum/current`
- `flarum_user`: flarum os user. `nginx` and `php-fpm` will use this user instead of `www-data` . example: `flarum`
- `flarum_project_root`: flarum project root folder. it recommended to use [deployer](https://deployer.org/) for deploying flarum. use [deploy.php](deploy.php) file as recipe. example: `/var/www/html/flarum`
- `flarum_log_path`: optional. flarum log files folder for promtail. example: `/var/www/html/flarum/current/storage/logs/*.log`
- `promtail_setup`: optional. flag to set up `promtail`. example: `false`
- `promtail_url`: optional. promtail http logs push url. example: `https://<id>:<token>@logs-prod-<server-id>.grafana.net/loki/api/v1/push`
- `promtail_environment`: optional. promtail log tag for env. example: `production`
- `fail2ban_setup`: optional. flag to set up `fail2ban`. example: `false`
- `deployer_user`: optional. if your `SSH_SUSER` in [deploy.php](deploy.php) different then `FLARUM_USER`. example: `ubuntu`

## Running this Playbook

Quick Steps:

### 1. Obtain the playbook
```shell
git clone https://github.com/hacker-land/ansible-flarum.git
```

### 2. Customize Options

```shell
nano vars/yoursite.yml
```

```yml
#vars/yoursite.yml
---
php_version: "8.3"
mysql_root_password: "mysql_root_password"
mysql_flarum_user: "flarum_user"
mysql_flarum_password: "flarum_password"
mysql_flarum_database: "flarum_database"
mysql_datadir_changed: false
mysql_datadir: "/var/lib/mysql"
mysql_innodb_buffer_pool_size: "12G" # set to 80% of RAM
mysql_innodb_log_file_size: "12G"    # set to 80% of RAM
mysql_innodb_log_buffer_size: "128M"
nginx_http_host: "yoursite"
nginx_http_conf_name: "yoursite.conf"
nginx_http_port: "80"
nginx_healthcheck_root: "/var/www/html/health-check"
# assuming you are using php-deployer to deploy, which creates 'current' directory in project
nginx_flarum_root: "/var/www/html/flarum/current"
# command to check if {{ flarum_user }} can access your project
# sudo -u flarum namei {{ flarum_project_root }}public/index.html
# use 'www-data' as flarum_user if you don't want to use a different user.
flarum_user: "flarum"
flarum_project_root: "/var/www/html/flarum"
flarum_log_path: "/var/www/html/flarum/current/storage/logs/*.log"
# optional playbooks
promtail_setup: false
promtail_url: "https://<id>:<token>@logs-prod-<server-id>.grafana.net/loki/api/v1/push"
promtail_environment: "production"
fail2ban_setup: false
deployer_user: "ubuntu"
```

### 3. Run the Playbook

#### Manual run on local

```command
ansible-playbook -l [target] -i [inventory file] -u [remote user] playbook.yml

# for local
ansible-playbook --connection=local --inventory 127.0.0.1, playbook.yml --ask-become-pass -e @./vars/yoursite.yml
```

#### GitHub Action Pipeline

Add below code in your flarum project `<flarum_project>/.github/workflows/deploy.yml`. Also, for deploying using `php deployer` you can use [deploy.php](/deploy.php) provided in this project.


```yaml
name: production-deploy
concurrency: production

on:
  push:
    branches: [ production ]
  workflow_dispatch:

env:
  PHP_VERSION: '8.3'
  SSH_USER: 'ubuntu'
  FLARUM_USER: 'yoursite_user' # use 'www-data' if you don't want to change

jobs:
  configure:
    runs-on: ubuntu-latest
    name: 'production: configure'

    steps:
    - uses: actions/checkout@v4
      with:
        repository: hacker-land/ansible-flarum
        ref: main
        path: '.ansible'

    - name: Add playbook variable
      run: |-
        cd .ansible
        touch vars/yoursite.yml
        
        # Refer vars/default.yml for default values
        echo '---' >> vars/yoursite.yml
        echo 'php_version: "${{env.PHP_VERSION}}"' >> vars/yoursite.yml
        echo 'mysql_root_password: "${{secrets.MYSQL_ROOT_PASSWORD}}"' >> vars/yoursite.yml
        echo 'mysql_flarum_user: "yoursite"' >> vars/yoursite.yml
        echo 'mysql_flarum_password: "${{secrets.MYSQL_FLARUM_PASSWORD}}"' >> vars/yoursite.yml
        echo 'mysql_flarum_database: "yoursite"' >> vars/yoursite.yml
        echo 'mysql_datadir_changed: true' >> vars/yoursite.yml
        echo 'mysql_datadir: "/mnt/data/mysql"' >> vars/yoursite.yml
        echo 'mysql_innodb_buffer_pool_size: "12G"' >> vars/yoursite.yml
        echo 'mysql_innodb_log_file_size: "12G"' >> vars/yoursite.yml
        echo 'mysql_innodb_log_buffer_size: "128M"' >> vars/yoursite.yml
        echo 'nginx_http_host: "yoursite.com"' >> vars/yoursite.yml
        echo 'nginx_http_conf_name: "yoursite.com.conf"' >> vars/yoursite.yml
        echo 'nginx_http_port: "80"' >> vars/yoursite.yml
        echo 'nginx_healthcheck_root: "/var/www/html/health-check"' >> vars/yoursite.yml
        echo 'nginx_flarum_root: "/var/www/html/yoursite/current"' >> vars/yoursite.yml
        # flarum_user can be 'www-data' if don't want to create custom user
        echo 'flarum_user: "${{env.FLARUM_USER}}"' >> vars/yoursite.yml
        echo 'flarum_project_root: "/var/www/html/yoursite.com"' >> vars/yoursite.yml
        echo 'flarum_log_path: "/var/www/html/yoursite.com/current/storage/logs/*.log"' >> vars/yoursite.yml
        echo 'promtail_setup: true' >> vars/yoursite.yml
        echo 'promtail_url: "https://<id>:<token>@logs-prod-<server-id>.grafana.net/loki/api/v1/push"' >> vars/yoursite.yml
        echo 'promtail_environment: "production"' >> vars/yoursite.yml
        echo 'fail2ban_setup: true' >> vars/yoursite.yml
        # If your SSH_USER to run php deployer is different than FLARUM_USER
        echo 'deployer_user: "${{env.SSH_USER}}"' >> vars/yoursite.yml

    - name: Run playbook
      uses: dawidd6/action-ansible-playbook@v2
      with:
        # Required, playbook filepath
        playbook: playbook.yml
        # Optional, directory where playbooks live
        directory: ./.ansible
        # Optional, ansible configuration file content (ansible.cfg)
        configuration: |
          [defaults]
          callbacks_enabled = ansible.posix.profile_tasks, ansible.posix.timer
          stdout_callback = yaml
          nocows = false
        # Optional, SSH private key
        key: ${{secrets.PRODUCTION_SSH_PRIVATE_KEY}}
        # Optional, literal inventory file contents
        inventory: |
          [all]
          '${{ vars.PRODUCTION_SSH_HOST }}'

          [yoursite]
          '${{ vars.PRODUCTION_SSH_HOST }}'
        # Optional, SSH known hosts file content
        options: |
          --inventory .hosts
          --limit yoursite
          -u ubuntu
          -e @./vars/yoursite.yml
          --verbose
  deploy:
    runs-on: ubuntu-latest
    name: 'production: deploy'
    needs: ['configure']

    steps:
      - uses: actions/checkout@v4
        with:
          ref: production

      - name: Setup PHP
        uses: shivammathur/setup-php@master
        with:
          php-version: ${{ env.PHP_VERSION }}

      - name: Cache Composer packages
        id: composer-cache
        uses: actions/cache@v4
        with:
          path: vendor
          key: ${{ runner.os }}-php-${{ hashFiles('**/composer.lock') }}
          restore-keys: |
            ${{ runner.os }}-php-

      - name: Install PHP dependencies
        run: composer install --prefer-dist --no-progress --no-suggest --no-scripts
      
      # Note: add php deployer as a dependency in your flarum project and use deploy.php file provided here as a recipe.
      - name: Deploy
        uses: deployphp/action@v1
        env:
          PROJECT_PATH: '/var/www/html/yoursite.com'
          SSH_HOST: '${{ vars.PRODUCTION_SSH_HOST }}'
        with:
          private-key: ${{ secrets.PRODUCTION_SSH_PRIVATE_KEY }}
          dep: deploy -v
```