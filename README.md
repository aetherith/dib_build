# Drupal In a Box Builder
This is a small custom script which I designed to speed up the deployment of
Drupal projects on multiple platforms. It supports only a small subset of
operations and most of it's functionality comes from its ability to run other
tools like Drush.

# Installation
The repository should be cloned and a minimum of _build.php_, _build.ini_, and 
a single _.run_ file should be copied to the _sites/_ directory of the Drupal 
installation.

# Configuration
All configuration is done with flat text files to promote portability and reduce
complexity. They should be encoded in UTF-8 and may use Unix or Windows new
line syntax. The _.run_ file names listed are only suggestions and any number
may be created for various administrative tasks.

## build.ini
This file acts as a global registry of variables used for replacement in
commands as they execute. It follows standard INI format and divides different 
environments into their own INI section. The special section **[master]** only 
contains a single variable _build_ at present. This variable defines which 
sub-section of variables should be used for this command run. This variable 
also allows a special flag value of '_all_' which instructs the script to run 
in batch mode and execute the command file with all the available sets of 
variables.

    ```ini
    [master]
    build           = dev

    [dev]
    ; For Windows
    findstr         = findstr
    ; For Linux
    ; findstr       = grep
    database        = db_dev
    username        = admin_user
    password        = admin_password

    [test]
    ; For Windows
    findstr         = findstr
    ; For Linux
    ; findstr       = grep
    database        = db_test
    username        = admin_user
    password        = admin_password
    ```

## .run Files
These files are the scripts that the program runs to complete administration and
deployment tasks. The examples below are illustrative but not exhaustive. Any 
number of additional scripts could be defined depending on the tasks required.
### install.run

### backup.run

### restore.run

### upgrade.run

# Use

