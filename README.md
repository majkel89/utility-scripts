# utility_scripts

Various utility scripts

## Installation

Clone repository

````bash
git clone git@github.com:majkel89/utility_scripts.git
````

Install composer dependencies

````bash
cd utility_scripts
composer install -o
````

Add project to path

````bash
export PATH=$PATH:$(pwd)
````

Than use

````bash
utility_scripts.php --list
````

## List of scripts

### git:add-ref-to-commit-msg

Automatically adds task reference to commit message from current branch name.

#### Usage

````bash
utility_scripts.php git:add-ref-to-commit-msg COMMIT_MSG_FILE
````

#### Help

````bash
utility_scripts.php git:add-ref-to-commit-msg --help
````