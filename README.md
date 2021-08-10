<p align="center">
    <img width="800" src=".github/logo.png" title="Project logo"><br />
    <img src="https://img.shields.io/maintenance/yes/2021?style=for-the-badge" title="Project status">
    <img src="https://img.shields.io/github/workflow/status/Dovyski/deployer/ci.uffs.cc?label=Build&logo=github&logoColor=white&style=for-the-badge" title="Build status">
</p>

# Deployer

Simple command-line tool that aims to facilitate the continous delivery of PHP apps, particularly [Laravel](https://laravel.com) apps. Imagine you want to update your app (in a remote server) when a new commit is pusehd to its repo, but you have no way of ssh'ing into the server or using something like Github Actions. `deployer` is a cli that can run periodically on the server to check if new code is avaiable, pulling it and executing all the migrations accordingly.

> **NOTICE:** `deployer` is a homebrewed solution to solve a very specific problem, there are better ways of doing continous delivery.

## 🚀 Usage

Before continuing, make sure you have `git`, `php` and `composer` installed and available.

### 1. Installation

Clone this repository to a folder in your server (where the to-be-deployed app will run):

```
git clone --recurse-submodules https://github.com/Dovyski/deployer && cd deployer
```

Optionally you can make the script globally available to save some typing. First make it executable:

```
chmod +x deployer.php
```

Then create an alias for it on your `.bash_profile`:

```
echo "alias dpr='/usr/bin/env php $(pwd)/deployer.php'" >> ~/.bash_profile
```

Source the alias:

```
source ~/.bash_profile
```

Finally check if everything is working as intended:

```
dpr -v
```

### 2. Deployment of an app

`deployer` expects two folders are available (and writable) to store files, such as database backups and logs, etc. It is highly recommended to create them.

Somewhere in your system, create the working folders:

```
mkdir {backups,logs}
```

Assuming your app is stored in the `/home/user/apps/myapp` (and that it is a cloned git repo), the command to update the app is:

```
dpr --app-dir="/home/user/apps/myapp" --backup-dir="/path/to/backups/folder" --log-dir="/path/to/logs/folder"
```

That's it! Your app should be up-to-date and running its latest version.

If you want to periodically update the app, making backups before running any migration, you can add the command above to a crontab:

```
crontab -e
```

Add the following:

```
* * * * * dpr --app-dir="/home/user/apps/myapp" --backup-dir="/path/to/backups/folder" --log-dir="/path/to/logs/folder"
```

In that case, `deployer` will only run migrations, create backups, etc. if the local version of the app is behind the remote repository. If it is even, nothing will happen locally.

### 3. Advanced usage (optional)

#### 3.1 Init Laravel app

If you are deploying a Laravel app, `deployer` can be used to init everything for you. In that case, run the following:

```
dpr --app-dir="/home/user/apps/myapp" --backup-dir="/path/to/backups/folder" --log-dir="/path/to/logs/folder" --init="laravel"
```

#### 3.2 Backup a running app

If you just want to backup the database of an app that is already running, without updating its code or migrating anything, just run:

```
dpr --app-dir="/home/user/apps/myapp" --backup-dir="/path/to/backups/folder" --log-dir="/path/to/logs/folder" --backup-only
```

#### 3.3 Dry-run

If you are afraid of running anything, you can check what is going to happen by using the `--dry-run` param:

```
dpr --app-dir="/home/user/apps/myapp" --backup-dir="/path/to/backups/folder" --log-dir="/path/to/logs/folder" --init=laravel --dry-run
```

In such case `deployer` will output all commands it intends to run, however no command will actually be executed.


## 🤝 Contribute

Your help is most welcome regardless of form! Check out the [CONTRIBUTING.md](CONTRIBUTING.md) file for all ways you can contribute to the project. For example, [suggest a new feature](https://github.com/Dovyski/deployer/issues/new?assignees=&labels=&template-english=feature_request.md&title=), [report a problem/bug](https://github.com/Dovyski/deployer/issues/new?assignees=&labels=bug&template-english=bug_report.md&title=), [submit a pull request](https://help.github.com/en/github/collaborating-with-issues-and-pull-requests/about-pull-requests), or simply use the project and comment your experience. You are encourage to participate as much as possible, but stay tuned to the [code of conduct](./CODE_OF_CONDUCT.md) before making any interaction with other community members.

See the [ROADMAP.md](ROADMAP.md) file for an idea of how the project should evolve.

## 🎫 License

This project is licensed under the [MIT](https://choosealicense.com/licenses/mit/) open-source license and is available for free.

## 🧬 Changelog

See all changes to this project in the [CHANGELOG.md](CHANGELOG.md) file.

## 🧪 Similar projects

Below is a list of interesting links:

* [Github Actions](https://github.com/features/actions)
