# ansible-flarum

This playbook will install a LEMP environment + Flarum on an Ubuntu 22.04 machine.
A virtualhost will be created with the options specified in the `vars/default.yml` variable file.

## Settings

- `mysql_root_password`: the password for the MySQL root account.
- `http_host`: your domain name.
- `http_conf`: the name of the configuration file that will be created within nginx.
- `http_port`: HTTP port, default is 80.


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
http_host: "your_domain"
http_conf_name: "your_domain.conf"
http_port: "80"
healthcheck_root: "/var/www/html/health-check"
flarum_project_root: "/var/www/html/flarum"
```

### 3. Run the Playbook

#### Manual run on local

```command
ansible-playbook -l [target] -i [inventory file] -u [remote user] playbook.yml

# for local
ansible-playbook --connection=local --inventory 127.0.0.1, playbook.yml --ask-become-pass -e @./vars/local.yml
```

#### GitHub Action Pipeline

Add below code in your flarum project `<flarum_project>/.github/workflows/deploy.yml`

```yaml
name: production-deploy
concurrency: production

on:
  push:
    branches: [ production ]
  workflow_dispatch:

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

        echo '---' >> vars/yoursite.yml
        echo 'mysql_root_password: "${{secrets.MYSQL_ROOT_PASSWORD}}"' >> vars/yoursite.yml
        echo 'mysql_flarum_user: "yoursite"' >> vars/yoursite.yml
        echo 'mysql_flarum_password: "${{secrets.MYSQL_FLARUM_PASSWORD}}"' >> vars/yoursite.yml
        echo 'mysql_flarum_database: "yoursite"' >> vars/yoursite.yml
        echo 'mysql_datadir_changed: true' >> vars/yoursite.yml
        echo 'mysql_datadir: "/mnt/data/mysql"' >> vars/yoursite.yml
        echo 'mysql_innodb_buffer_pool_size: "12G"' >> vars/yoursite.yml
        echo 'mysql_innodb_log_file_size: "12G"' >> vars/yoursite.yml
        echo 'mysql_innodb_log_buffer_size: "128M"' >> vars/yoursite.yml
        echo 'http_host: "yoursite.com"' >> vars/yoursite.yml
        echo 'http_conf_name: "yoursite.com.conf"' >> vars/yoursite.yml
        echo 'http_port: "80"' >> vars/yoursite.yml
        echo 'flarum_project_root: "/var/www/html/yoursite.com"' >> vars/yoursite.yml


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
        key: ${{secrets.SSH_PRIVATE_KEY}}
        # Optional, literal inventory file contents
        inventory: |
          [all]
          <your server ip>

          [yoursite]
          <your server ip>
        # Optional, SSH known hosts file content
        options: |
          --inventory .hosts
          --limit yoursite
          -u ubuntu
          -e @./vars/yoursite.yml
          --verbose
```