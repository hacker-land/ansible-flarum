<?php
# Refer: https://discuss.flarum.org/d/32586-automatic-deployment-from-github-with-deployer
namespace Deployer;

require 'recipe/common.php';
require 'contrib/cachetool.php';
require 'contrib/rsync.php';
require 'contrib/crontab.php';
require 'recipe/deploy/cleanup.php';

// Config
set('repository', '');
set('keep_releases', 3);
set('http_user', getenv('FLARUM_USER'));

// Shared files/dirs between deploys
add('shared_files', [
    'config.php',
]);
add('shared_dirs', [
    'public/assets',
    'public/sitemaps',
    'storage'
]);

// Writable dirs by web server
set('writable_use_sudo', true);
set('writable_mode', 'chown');
add('writable_dirs', [
    'public/assets',
    'public/sitemaps',
    'storage'
]);

// Hosts
host(getenv('SSH_HOST'))
    ->set('remote_user', getenv('SSH_USER'))
    ->set('deploy_path', getenv('PROJECT_PATH'))
    ->set('port', 22)
    ->set('rsync_src', getenv('GITHUB_WORKSPACE'))
    ->set('rsync_dest','{{release_path}}');

// Rsync Configuration
set('rsync', [
    'exclude' => [
        'config.php',
        '.git',
        '.ansible',
        '.github',
        'deploy.php',
        '.env',
        'storage',
        'public/assets',
        'public/sitemaps',
        'vendor'
    ],
    'exclude-file' => false,
    'include'      => [],
    'include-file' => false,
    'filter'       => [],
    'filter-file'  => false,
    'filter-perdir'=> false,
    'options'      => ['delete'],
    'timeout'      => 60,
    'flags' => 'rzcE'
]);

// Tasks
desc('Deploy your project using rsync');
task('deploy:update_code')->disable();
task('deploy', [
    'deploy:prepare',
    'rsync',
    'deploy:vendors',
    'deploy:publish',
]);

// Hooks
after('deploy:failed', 'deploy:unlock');

set('crontab:use_sudo', true);
after('deploy:success', 'crontab:sync');
add('crontab:jobs', [
    '* * * * * cd {{current_path}} && sudo -u {{http_user}} {{bin/php}} flarum schedule:run >> /dev/null 2>&1',
]);

// ------------------------------------------------------------- Task: cachetool
set('bin/cachetool', '/usr/local/bin/cachetool.phar');
set('cachetool', '/var/run/php/php8.3-fpm.sock');
after('deploy:symlink', 'cachetool:clear:opcache');
after('deploy:symlink', 'cachetool:clear:stat');


// ---------------------------------------------------------------- Task: deploy
desc('Call flarum to rebuild cache.');
task('deploy:rebuild_cache', function () {
    if (file_exists(parse("{{release_path}}/config.php"))) {
        run('sudo -u {{http_user}} {{bin/php}} {{release_path}}/flarum migrate');
        run('sudo -u {{http_user}} {{bin/php}} {{release_path}}/flarum assets:publish');
        run('sudo -u {{http_user}} {{bin/php}} {{release_path}}/flarum cache:clear');
    }
});
before('deploy:symlink', 'deploy:rebuild_cache');

desc('Set ownership to web server user: {{http_user}}.');
task('deploy:owner', function () {
    $httpUser = get('http_user');

    if (null === $httpUser) {
        $httpUser = run("ps axo user,comm | grep -E '[a]pache|[h]ttpd|[_]www|[w]ww-data|[n]ginx' | grep -v root | head -1 | cut -d\  -f1");
    }
    #runs as root
    run('sudo chown -R ' . $httpUser . ':' . $httpUser . ' {{deploy_path}}');

    run('sudo chmod -R 775 {{deploy_path}}');
});
after('deploy:cleanup', 'deploy:owner');


desc('Copy config.php from previous release.');
task('deploy:config:copy', function () {
    $latest = within('{{deploy_path}}', function () {
        return run('cat .dep/latest_release || echo 0');
    });

    if ($latest != '0') {
        $sharedConfigPath = "{{deploy_path}}/shared/config.php";

        if (!test("[ -f $sharedConfigPath ]")) {
            $releaseConfigPath = "{{deploy_path}}/releases/{$latest}/config.php";
            run("if [ -f $(echo $releaseConfigPath) ]; then cp $releaseConfigPath {{deploy_path}}/shared; fi");
        }
    }
});
before('deploy:prepare', 'deploy:config:copy');