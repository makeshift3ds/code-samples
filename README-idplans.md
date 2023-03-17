# Remote Property Manager - MySQL Programming Interface

This application is used to generate the schema for the database, as well as all alters and imports.

## Requirements

* [Docker](https://www.docker.com/get-docker)

## Installation

### Development
This is handled by the dev-environments docker-compose. View the readme in dev-environment for more information.

### Production/Staging/Feature
The Amazon RDS Aurora instances are generated via the cloudformation template located in the rpm-infrastructure repository.

## Usage

This application uses the sequelize npm package to create an environment with higher order functions that make it easier for managing the database. Create migrations for any changes to the database schema (aka structure). This ensures that the database can be rebuilt, without having to be restored from a backup and provides documentation for any alterations. 

### Development

The mysql docker container exposes an mysql server that is available locally at `http://localhost:3306`. 

The username is `dev` and the password is `devpass` by default.

### Production

Look to the AWS console to find the location of the latest RDS Aurora instance. There will be a primary instance which is used for writes and at least one read replica which is used for reads.

## Importing Data

* [Procedural Import](https://docs.aws.amazon.com/AmazonRDS/latest/UserGuide/MySQL.Procedural.Importing.SmallExisting.html)

## Rollback

Database snapshots are done daily. Should an issue arise the snapshot should be restored through the AWS console.

## Infrastructure Information

The infrastructure is built with Cloudformation using AWS RDS Aurora instances. Instance sizes are managed through the cloudformation template and alters should be done there.

## Dev Information

The AWS RDS server is only accessible through services within the same VPC, which means it can not be connected to directly. A bastion EC2 instance exists (`v5-bastion`) which has SSH enabled and uses the `v6-key` private key. Once connected to the bastion you can run mysql commands against the production database. To connect to the database from local development you need to use a tcp/ip with ssh connection.

Please contact the Lead Developer for the crendentials necessary to connect to the database. Note to lead developer: they are stored in AWS Secrets and used in the bastions cloudformation template.

*Example ssh command:*
`ssh -i ~/.ssh/v6-key.pem ec2-54-165-137-15.compute-1.amazonaws.com`

*Example mysql command*
`mysql -h path.to.us-east-1.rds.amazonaws.com -uusername -ppassword`

## Creating the Database

Sequelize will create the database via `sequelize db:create`, it is important to use the right collation so the database is case sensitive because the original dynamodb is case sensitive. See 'ShopCore Properties' vs 'Shopcore Properties' for an example. 

`Database Encoding: latin_1`

`Database Collation: latin1_general_cs`

## Importing Data

Create an SSH tunnel through the bastion to the db writer instance
`ssh -i ~/.ssh/v6-key.pem -L 3307:vd1y1o9jnfam9z1.c0irbh2453xt.us-east-1.rds.amazonaws.com:3306 instance-username@rpm-bastion.idplans.com -N`

Connect to localhost:3307 to import data
`mysql v6 -h 127.0.0.1 -P 3307 -uidpadmin -pidpadmin! < backup.sql`

The above are the instructions for staging. Change values to production values if you need to update production. 

## Exporting Production Data

There is a script called `sync-with-production-dynmodb` that will make refresh a local database with the most recently dynamodb data. It *does not* take into account the new data in the RDS, it was built strictly to create a starting point for the RDS (make it current). However it demonstrates how to access the database and manage data using Sequelize.

For your convience the process has been codified into the `package.json` file in order of precedence.

Run the first command to start the sync process on all of the DynamoDb Tables. This will store output files in the script directory so they can be reviewed for discrepancies.
`npm run import:all`

Next start reprocessing the Acls. This will output some data to text files that are prefixed with 'output'. These are used by the next script to further recalculate the acls.
`npm run acls:recalculate`

After the acl recalculation. Run the import script. It doesn't actually add the data to the table, it is output into a text file called 'insert-statements.sql'. This contains insert statements for all of the acls records.
`npm run acls:import`

After this has been generated, the acls, roles and roleAction tables need to be cleared out and their data needs to be updated to the expected format. Making a copy of Acls and Role table is recommended (so the sync does not need to be run again should any error occur).
```
SET FOREIGN_KEY_CHECKS=0;
truncate Acls;
truncate Roles;
truncate RoleActions;
SET FOREIGN_KEY_CHECKS=1;
``` 

Now the Roles and Role Actions need to be reseeded. Run these sequelize commands to run the seeders.
`sequelize db:seed --seed 20181113025420-roles.js`
`sequelize db:seed --seed 20181113026420-role-actions.js`

Run the queries found in the `insert-statements.sql` file manually in Sequel Pro. [Copy, Paste, Run All]

Next the page backgrounds have to be converted. This will populate the `PagePages` table.
`npm run pages:relationships`

## Exporting from Local Database

Because the database is in docker, you need to connect through docker. Here is the command I have been running. Your environment might be different.

`docker exec mysql-container /usr/bin/mysqldump -u dev --password=devpass v6 > backup.sql`

Alternatively you can just export from your favorite sql program, e.g. Sequel Pro.