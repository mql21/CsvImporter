## About this project 

ORM's are a helpful tool to operate with database tables in a higher level, through what we normally call "entities" . These, at the end of the day, are just classes. This way we can manipulate instances of those classes that will ultimately be persisted to our DB, without needing to write the entire SQL code.

Surely ORM's such as Doctrine or Propel are great and have their advantages. However, when you need to work with large datasets (i.e. importing lots of records via CSV files), sometimes they are not very helpful. Doctrine has mechanisms for [Batch Processing](https://www.doctrine-project.org/projects/doctrine-orm/en/2.7/reference/batch-processing.html#batch-processing), but still huge datasets can take quite a while to be processed. 

This project's main goal is to provide a solution to import large CSV files taking as few time as possible. At this point this project is ready to be used in Symfony (4.3 or higher) applications, however support for other PHP frameworks may come in the near future.

## Installation

To install this project, just require the dependency with composer:

```
composer require mql21/csv-importer:dev-master
```


## Setting up CsvImporter

Before using CsvImporter, it needs to be set up.

First off, you need to allow CsvImporterInterface to be injected as a service throughout your application, add this line to the `services.yaml` file:
```
mql21\CsvImporter\CsvImporterInterface: ~
```
Now configure CsvImporter dependencies, add:
```
mql21\CsvImporter\Builder\CsvImporterMysqlBuilder:
    arguments:
        $csvMappingFields: "%csv_mapping_fields%"

csv_importer.mysql_builder:
    class: mql21\Adapter\CsvImporter\Builder\CsvImporterMysqlBuilder

mql21\CsvImporter\Adapter\CsvImporterMysqlAdapter:
    arguments:
        $csvImporterMysqlBuilder: "@csv_importer.mysql_builder"

csv_importer.mysql_adapter:
    class: mql21\CsvImporter\Adapter\CsvImporterMysqlAdapter
```

Then, you can simply autowire the MySQL adapter to your controller, service or wherever you desire in your app:
```
App\Service\Import\MyImportService:
    arguments:
        $csvImporter: "@csv_importer.mysql_adapter"
```


## Using CsvImporter

CSV data needs to be defined in `services.yaml` so that the importer knows what the mapping between the CSV and the database is. To do so, you can define the following config under the `parameters` section:

```
parameters:
    csv_mapping_fields:
        test.person: ## database.destination_table_name
            name: ## csv column name
                column_name: 'name' ## database table name
                required: true ## cannot be empty in csv
            surname:
                column_name: 'surname'
                required: true
```
All CSV configs need to be defined under the `csv_mapping_fields` scope.

Now you can inject `CsvImporterInterface` throughout your app and perform the import as follows:

```
$csvPath = "some/csv/path/file.csv";
$tableName = "test.person";
$completeMessage = $csvImporter->import($csvPath, $tableName);
```

TIP: Make sure to always inject `CsvImporterInterface` to follow Dependency Inversion Principle.

## Some interesting data

Note: As you might have noticed, `$csvImporter` isn't declared explicitly since it's meant to be injected via DI. 


As the following chart clearly shows, persisting entities into the database using Doctrine's EntityManager can take some time, especially if we're working with relatively large data sets:

![Alt text](img/time_doctrine.png?raw=true)

As you can see, it took around **11.5 minutes** (691.15 seconds) to write 10000 records only.

However, because CsvImporter writes the entire data using a native query, the time it takes now is almost insignificant:

![Alt text](img/time_native.png?raw=true)

Also, if we try to import even larger data sets, we can appreciate how import time is pretty low too:

![Alt text](img/time_native_large_datasets.png?raw=true)

As we can see it only takes around **4.7 seconds to import 1 million records**.

Side note: This data was collected from a local MySQL database installed in a computer with 16GB of RAM.

## Further work and contributing to the project

Any feedback, fix or contribution to this project is welcome. If you want to contribute please feel free to open a pull request and it will be checked as soon as possible. 

Here's a list of TODO's that can be done right now:

* Adding support for related tables. CsvImporter is meant to be used to write data into a single table, so relations aren't implemented yet. It would be interesting to add some sort of mechanism to allow the possibility to write into multiple related tables.
* Adding some unit testing.
* Adding more field validations. At this point, there is only two validations: required and decimal. Any other field validation would be very welcome.
* Making this library framework-agnostic. Now it is clearly implemented to work along with Symfony, but it would be interesting to add support for other frameworks as well. 

