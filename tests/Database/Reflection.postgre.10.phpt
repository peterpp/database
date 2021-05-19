<?php

/**
 * Test: PostgreSQL 10+ SERIAL and IDENTITY imply autoincrement on primary keys
 * @dataProvider? databases.ini  postgresql
 */

declare(strict_types=1);

use Tester\Assert;
use Tester\Environment;

require __DIR__ . '/connect.inc.php'; // create $connection


$ver = $connection->query('SHOW server_version')->fetchField();
if (version_compare($ver, '10') < 0) {
	Environment::skip("For PostgreSQL 10 or newer but running with $ver.");
}


Nette\Database\Helpers::loadFromFile($connection, Tester\FileMock::create('
	DROP SCHEMA IF EXISTS "reflection_10" CASCADE;
	CREATE SCHEMA "reflection_10";

	CREATE TABLE "reflection_10"."serial" ("id" SERIAL);
	CREATE TABLE "reflection_10"."serial_pk" ("id" SERIAL PRIMARY KEY);

	CREATE TABLE "reflection_10"."identity_always" ("id" INTEGER GENERATED ALWAYS AS IDENTITY);
	CREATE TABLE "reflection_10"."identity_always_pk" ("id" INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY);

	CREATE TABLE "reflection_10"."identity_by_default" ("id" INTEGER GENERATED BY DEFAULT AS IDENTITY);
	CREATE TABLE "reflection_10"."identity_by_default_pk" ("id" INTEGER GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY);
'));
$driver = $connection->getDriver();


function filter(array $columns): array
{
	return array_map(function (array $col): array {
		return [
			'name' => $col['name'],
			'autoincrement' => $col['autoincrement'],
			'sequence' => $col['vendor']['sequence'],
		];
	}, $columns);
}


// Autoincrement columns by SERIAL and IDENTITY
$connection->query('SET search_path TO reflection_10');

$columns = [
	'serial' => filter($driver->getColumns('serial')),
	'serial_pk' => filter($driver->getColumns('serial_pk')),
	'identity_always' => filter($driver->getColumns('identity_always')),
	'identity_always_pk' => filter($driver->getColumns('identity_always_pk')),
	'identity_by_default' => filter($driver->getColumns('identity_by_default')),
	'identity_by_default_pk' => filter($driver->getColumns('identity_by_default_pk')),
];


Assert::same([
	'serial' => [[
		'name' => 'id',
		'autoincrement' => false,
		'sequence' => 'serial_id_seq',
	]],

	'serial_pk' => [[
		'name' => 'id',
		'autoincrement' => true,
		'sequence' => 'serial_pk_id_seq',
	]],

	'identity_always' => [[
		'name' => 'id',
		'autoincrement' => false,
		'sequence' => 'identity_always_id_seq',
	]],

	'identity_always_pk' => [[
		'name' => 'id',
		'autoincrement' => true,
		'sequence' => 'identity_always_pk_id_seq',
	]],

	'identity_by_default' => [[
		'name' => 'id',
		'autoincrement' => false,
		'sequence' => 'identity_by_default_id_seq',
	]],

	'identity_by_default_pk' => [[
		'name' => 'id',
		'autoincrement' => true,
		'sequence' => 'identity_by_default_pk_id_seq',
	]],
], $columns);
