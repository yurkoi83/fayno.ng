<?php

namespace App\Helpers\Common\DBUtils;

use App\Helpers\Common\DBUtils;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DBRawIndex
{
	/**
	 * Get full index name powered by Laravel
	 *
	 * @param string $tableName
	 * @param string|array $indexName
	 * @param string $indexType
	 * @return string
	 */
	public static function getRawIndexName(string $tableName, string|array $indexName, string $indexType = 'index'): string
	{
		$tablePrefix = DBUtils::getRawTablePrefix();
		$indexNameAsString = self::getStandardFormattedIndexName($indexName);

		$fullIndexName = $tablePrefix . $tableName . '_' . $indexNameAsString . '_' . $indexType;
		$fullIndexName = self::unwrapWithBacktick($fullIndexName);

		return castToString($fullIndexName);
	}

	/**
	 * Check if index exists (Raw SQL)
	 *
	 * @param string $tableName
	 * @param string|array $indexName
	 * @param string $indexType
	 * @return bool
	 */
	public static function rawDoesIndexExist(string $tableName, string|array $indexName, string $indexType = 'index'): bool
	{
		$isMariaDb = DBUtils::isMariaDB();
		if ($isMariaDb) {
			return self::doesMariaDBIndexExist($tableName, $indexName, $indexType);
		} else {
			return self::doesMysqlIndexExist($tableName, $indexName, $indexType);
		}
	}

	/**
	 * Check if MySQL index exists (Raw SQL)
	 *
	 * @param string $tableName
	 * @param string|array $indexName
	 * @param string $indexType
	 * @return bool
	 */
	public static function doesMysqlIndexExist(string $tableName, string|array $indexName, string $indexType = 'index'): bool
	{
		$tableNameWithPrefix = DB::getTablePrefix() . $tableName;
		$idxDb = DB::connection()->getDatabaseName();
		$indexNameAsString = self::getStandardFormattedIndexName($indexName);

		$sql = [
			'Key_name'   => 'SHOW INDEX FROM `' . $tableNameWithPrefix . '` FROM `' . $idxDb . '`;',
			'INDEX_NAME' => 'SELECT DISTINCT INDEX_NAME
						 FROM `INFORMATION_SCHEMA`.`STATISTICS`
						 WHERE `TABLE_SCHEMA` = \'' . $idxDb . '\'
							AND `TABLE_NAME` = \'' . $tableNameWithPrefix . '\'',
		];

		// Exception for MySQL 8
		$isMySql8OrGreater = (!DBUtils::isMariaDB() && DBUtils::isMySqlMinVersion('8.0'));
		$indexColumn = $isMySql8OrGreater ? 'INDEX_NAME' : 'Key_name';

		$results = DB::select($sql[$indexColumn]);

		if (is_array($results) && count($results) > 0) {
			$results = collect($results)
				->mapWithKeys(function ($item) use ($indexColumn) {
					$indexNameLocal = $item->{$indexColumn} ?? null;

					return [$indexNameLocal => $indexNameLocal];
				})
				->toArray();

			return in_array($indexNameAsString, $results);
		}

		return false;
	}

	/**
	 * Check if MariaDB index exists (Raw SQL)
	 *
	 * @param string $tableName
	 * @param string|array $indexName
	 * @param string $indexType
	 * @return bool
	 */
	public static function doesMariaDBIndexExist(string $tableName, string|array $indexName, string $indexType = 'index'): bool
	{
		$tableNameWithPrefix = DB::getTablePrefix() . $tableName;
		$idxDb = DB::connection()->getDatabaseName();
		$indexNameAsString = self::getStandardFormattedIndexName($indexName);

		$sql = 'show indexes from `' . $tableNameWithPrefix . '` in `' . $idxDb . '`;';
		$results = DB::select($sql);

		if (is_array($results) && count($results) > 0) {
			$results = collect($results)
				->mapWithKeys(function ($item) {
					$indexNameLocal = $item->Key_name ?? null;

					return [$indexNameLocal => $indexNameLocal];
				})
				->toArray();

			return in_array($indexNameAsString, $results);
		}

		return false;
	}

	/**
	 * Create (unique) index if not exists (Raw SQL)
	 *
	 * @param string $tableName
	 * @param string|array $indexName
	 * @param string $indexType
	 * @param bool $canValidateIndexColumn
	 * @return void
	 */
	public static function rawCreateIndexIfNotExists(
		string       $tableName,
		string|array $indexName,
		string       $indexType = 'index',
		bool         $canValidateIndexColumn = true
	): void
	{
		if (!Schema::hasTable($tableName)) {
			return;
		}

		if ($canValidateIndexColumn) {
			if (!DBIndex::hasColumn($tableName, $indexName)) {
				return;
			}
		}

		if (self::rawDoesIndexExist($tableName, $indexName, $indexType)) {
			return;
		}

		$tableNameWithPrefix = DB::getTablePrefix() . $tableName;
		$indexNameInLaravel = self::getRawIndexName($tableName, $indexName, $indexType);
		$columnsString = self::convertIndexArrayToCommaSeparatedString($indexName);

		if ($indexType == 'unique') {
			$sql = "ALTER TABLE `" . $tableNameWithPrefix . "` ADD UNIQUE INDEX `" . $indexNameInLaravel . "` (" . $columnsString . ");";
		} else {
			$sql = "ALTER TABLE `" . $tableNameWithPrefix . "` ADD INDEX `" . $indexNameInLaravel . "` (" . $columnsString . ");";
		}
		DB::unprepared($sql);
	}

	/**
	 * Drop index if exists (Raw SQL)
	 *
	 * @param string $tableName
	 * @param string|array $indexName
	 * @param string $indexType
	 * @return void
	 */
	public static function rawDropIndexIfExists(string $tableName, string|array $indexName, string $indexType = 'index'): void
	{
		$isMariaDb = DBUtils::isMariaDB();
		if ($isMariaDb) {
			self::dropMariaDBIndexIfExists($tableName, $indexName, $indexType);
		} else {
			self::dropMysqlIndexIfExists($tableName, $indexName, $indexType);
		}
	}

	/**
	 * Drop MySQL index if exists (Raw SQL)
	 *
	 * @param string $tableName
	 * @param string|array $indexName
	 * @param string $indexType
	 * @return void
	 */
	public static function dropMysqlIndexIfExists(string $tableName, string|array $indexName, string $indexType = 'index'): void
	{
		if (self::doesMysqlIndexExist($tableName, $indexName)) {
			$tableNameWithPrefix = DB::getTablePrefix() . $tableName;
			$indexNameAsString = self::getStandardFormattedIndexName($indexName);

			$sql = "ALTER TABLE `" . $tableNameWithPrefix . "` DROP INDEX " . $indexNameAsString . ";";
			DB::unprepared($sql);
		}
	}

	/**
	 * Drop MariaDB index if exists (Raw SQL)
	 *
	 * @param string $tableName
	 * @param string|array $indexName
	 * @param string $indexType
	 * @return void
	 */
	public static function dropMariaDBIndexIfExists(string $tableName, string|array $indexName, string $indexType = 'index'): void
	{
		if (self::doesMariaDBIndexExist($tableName, $indexName)) {
			$tableNameWithPrefix = DB::getTablePrefix() . $tableName;
			$indexNameAsString = self::getStandardFormattedIndexName($indexName);

			$sql = "DROP INDEX `" . $indexNameAsString . "` ON `" . $tableNameWithPrefix . "`;";
			DB::unprepared($sql);
		}
	}

	/**
	 * Convert index array to string
	 * e.g. Generate the index name based on Laravel's default naming convention
	 *
	 * @param string|array $indexName
	 * @return string
	 */
	public static function getStandardFormattedIndexName(string|array $indexName): string
	{
		$indexName = self::unwrapWithBacktick($indexName);

		return is_array($indexName) ? implode('_', $indexName) : $indexName;
	}

	/**
	 * Convert columns array to a comma-separated string
	 *
	 * @param string|array $indexName
	 * @return string
	 */
	public static function convertIndexArrayToCommaSeparatedString(string|array $indexName): string
	{
		$indexNamesList = !is_array($indexName) ? [$indexName] : $indexName;
		$indexNamesList = self::wrapWithBacktick($indexNamesList);

		return collect($indexNamesList)->implode(', ');
	}

	/**
	 * @param string|array $indexName
	 * @return array|string
	 */
	public static function wrapWithBacktick(string|array $indexName): array|string
	{
		if (is_string($indexName)) {
			return !str_starts_with($indexName, "`") ? "`$indexName`" : $indexName;
		}

		return collect($indexName)
			->map(fn ($item) => !str_starts_with($item, "`") ? "`$item`" : $item)
			->toArray();
	}

	/**
	 * @param string|array $indexName
	 * @return array|string
	 */
	public static function unwrapWithBacktick(string|array $indexName): array|string
	{
		if (is_string($indexName)) {
			return str_replace("`", '', $indexName);
		}

		return collect($indexName)
			->map(fn ($item) => str_replace("`", '', $item))
			->toArray();
	}
}
