# OrientDB Tools

Tools for working with OrientDB databases.

## Installation

  1. Install `Composer` (if not yet installed).
  0. Run `composer install` in the project's directory.
  0. Modify `env-config.json` to suit your environment's configuration.
    - The environment variable `ENV_NAME` determines which environment you are in.
        - Valid values are those specified as keys in `env-config.json`.
    - Note: do NOT save the modified file back to the git repo.
  0. Run any tool without arguments for more information about the tool.
    - Ex. `./orientdb-import.php`

## Which tools are available?

Currently, only one tool is available.

### orientdb-import

Allows importing data in CSV format into an OrientDB database.

##### Syntax

    orientdb-import.php [--env local|intranet|staging|production] [--clear] [--limit N] [--set {json}] database class input-file.csv

##### CSV format

  - 1st line must define field names.
  - Other lines define one record per line.
  - Fields should be delimited by commas.
  - String fields should be surrounded by double quotes.
  - Empty fields are imported as NULL.
  - If a field's value seems to be a valid number, it will be typecasted to a float.
  - If you need to import numbers as strings, enclose them in double qoutes.
  
##### Command-line options

###### --env

Force a specific configuration environment.
If not specified, the environment name will be determined by the `ENV_NAME` environmental variable.
If no variable is defined, the name defaults to `local`.

###### --clear

When specified, this option causes all existing records of the target class to be erased before the import begins.

###### --limit

Limit the maximum number of records to be imported.
If not specified, all records will be imported.

###### --set

Allows merging constant data into each record being imported.
The option's argument should be encoded as JSON.
You should escape spaces using `\`, otherwise a space will prematurely end the option's argument at that point.
