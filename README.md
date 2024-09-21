# ansible-flarum

This playbook will install a LEMP environment + Flarum on an Ubuntu 22.04 machine.
A virtualhost will be created with the options specified in the `vars/default.yml` variable file.

## Settings

- `mysql_root_password`: the password for the MySQL root account.
- `nginx_http_host`: your domain name.
- `nginx_http_conf_name`: the name of the configuration file that will be created within nginx.
- `nginx_http_port`: HTTP port, default is 80.


## Running this Playbook

Quick Steps:

### 1. Obtain the playbook
```shell
git clone https://github.com/hacker-land/ansible-flarum.git
```

### 2. Customize Options

```shell
nano vars/default.yml
```

```yml
#vars/default.yml
---
mysql_root_password: "mysql_root_password"
mysql_flarum_user: "flarum_user"
mysql_flarum_password: "flarum_password"
mysql_flarum_database: "flarum_database"
mysql_datadir: "/var/lib/mysql"
mysql_innodb_buffer_pool_size: "12G" # set to 80% of RAM
mysql_innodb_log_file_size: "12G"    # set to 80% of RAM
mysql_innodb_log_buffer_size: "128M"
nginx_http_host: "your_domain"
nginx_http_conf_name: "your_domain.conf"
nginx_http_port: "80"
nginx_healthcheck_root: "/var/www/html/health-check"
flarum_project_root: "/var/www/html/flarum/current"
```

### 3. Run the Playbook

#### Manual run on local

```command
ansible-playbook -l [target] -i [inventory file] -u [remote user] playbook.yml

# for local
ansible-playbook --connection=local --inventory 127.0.0.1, playbook.yml --ask-become-pass -e @./vars/local.yml
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