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

Before using CsvImporter, it needs to be set up. To do so, you need to specify the mapping between the CSV and the database table in the `services.yaml` file:

```
parameters:
    csv_mapping_fields:
        company: # Table name
            company_name: # CSV column name
                column_name: 'company_name' # Mapped database column name
                required: true # True if this field can not be empty in CSV
            registration_id:
                column_name: 'registration_id'
                required: true
```

In order to be able to inject CsvImporter as a service, register the following entry to the `services.yaml`:

```
CsvImporter\:
        resource: '../vendor/mql21/csv-importer/src/CsvImporter/*'
```

Then, the `csv_mapping_fields` var needs to be injected to the CsvImporter class. Just add the following configuration at the bottom of the `services.yaml` file:

```
CsvImporter\CsvImporter:
        arguments:
            $csvMappingFields: "%csv_mapping_fields%"
```


## Using CsvImporter

CsvImporter can be easily used as shown in the following example:

```
$file = $form['attachment']->getData();

$completeMessage = $csvImporter
    ->setCsvFilePath($file->getRealPath())
    ->setDestinationTable('my_table')
    ->enableOnDuplicateKeyUpdate()
    ->save();
```

`$form['atachment']` is a `FileType` form type that returns an `UploadedFile` instance containing the CSV file that we uploaded through the form.

To actually write the entire CSV into the database, you only need to call 4 methods:

* `setCsvFilePath($filepath)`: The filepath of the uploaded CSV. If you're using a form with a `FileType` like in the example, filepath can be obtained by calling the `getRealPath()` method.
* `setDestinationTable('my_table')`: The table where the CSV data will be written into.  This needs to match the exact name of the table in the database.
* `enableOnDuplicateKeyUpdate()`: This optional method will enable the `ON DUPLICATE KEY UPDATE` clause in the query that will be performed to write the data. This way, if any **unique key** (simple or compound) is duplicated, the row will be rather updated.
* `save()`: Writes the CSV data to the database.

Note: As you might have noticed, `$csvImporter` isn't declared explicitly since it's meant to be injected via DI. 

## Some interesting data

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

