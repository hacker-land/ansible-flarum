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

```command
ansible-playbook -l [target] -i [inventory file] -u [remote user] playbook.yml

# for local
ansible-playbook --connection=local --inventory 127.0.0.1, playbook.yml --ask-become-pass -e @./vars/local.yml
```