<?php

namespace App\Helpers\Common\DBUtils;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DBIndex
{
	/**
	 * Get full (unique) index name powered by Laravel
	 * getIndexName
	 * getLaravelFormattedIndexName
	 *
	 * @param string $tableName
	 * @param string|array $indexName
	 * @param string $indexType
	 * @return string
	 */
	public static function getLaravelFormattedIndexName(string $tableName, string|array $indexName, string $indexType = 'index'): string
	{
		$tablePrefix = DB::getTablePrefix();
		$indexNameAsString = DBRawIndex::getStandardFormattedIndexName($indexName);

		$fullIndexName = $tablePrefix . $tableName . '_' . $indexNameAsString . '_' . $indexType;
		$fullIndexName = DBRawIndex::unwrapWithBacktick($fullIndexName);

		return castToString($fullIndexName);
	}

	/**
	 * Check if (unique) index exists (Laravel)
	 * $indexType can be: index, unique
	 *
	 * @param string $tableName
	 * @param string|array $indexName
	 * @param string $indexType
	 * @return bool
	 */
	public static function doesIndexExist(string $tableName, string|array $indexName, string $indexType = 'index'): bool
	{
		if (!Schema::hasTable($tableName)) {
			return false;
		}

		$indexes = Schema::getIndexListing($tableName);

		// Check if manually naming index exists
		$indexNameAsString = DBRawIndex::getStandardFormattedIndexName($indexName);
		$found = in_array($indexNameAsString, $indexes);

		// If manually naming index is not found,
		// Check if automatic naming index exists
		if (!$found) {
			$indexNameInLaravel = self::getLaravelFormattedIndexName($tableName, $indexName, $indexType);
			$found = in_array($indexNameInLaravel, $indexes);
		}

		return $found;
	}

	/**
	 * Create (unique) index if not exists (Laravel)
	 *
	 * @param string $tableName
	 * @param string|array $indexName
	 * @param string $indexType
	 * @param bool $canValidateIndexColumn
	 * @return void
	 */
	public static function createIndexIfNotExists(
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
			if (!self::hasColumn($tableName, $indexName)) {
				return;
			}
		}

		if (self::doesIndexExist($tableName, $indexName, $indexType)) {
			return;
		}

		Schema::table($tableName, function (Blueprint $table) use ($tableName, $indexName, $indexType) {
			if ($indexType == 'unique') {
				$table->unique($indexName);                  // Automatic naming unique index
				// $table->unique([$indexName], $indexName); // Manually naming unique index
			} else {
				$table->index($indexName);                  // Automatic naming index
				// $table->index([$indexName], $indexName); // Manually naming index
			}
		});
	}

	/**
	 * Drop index if exists (Laravel)
	 *
	 * @param string $tableName
	 * @param string|array $indexName
	 * @param string $indexType
	 * @return void
	 */
	public static function dropIndexIfExists(string $tableName, string|array $indexName, string $indexType = 'index'): void
	{
		if (!Schema::hasTable($tableName) || !self::hasColumn($tableName, $indexName)) {
			return;
		}

		if (!self::doesIndexExist($tableName, $indexName, $indexType)) {
			return;
		}

		// Drop automatic naming index with '->dropUnique([$indexName])'
		try {
			Schema::table($tableName, function (Blueprint $table) use ($tableName, $indexName, $indexType) {
				$indexName = !is_array($indexName) ? [$indexName] : $indexName;
				if ($indexType == 'unique') {
					$table->dropUnique($indexName);
				} else {
					$table->dropIndex($indexName);
				}
			});
		} catch (Throwable $e) {
		}

		// Drop custom naming index with '->dropUnique($indexName)'
		try {
			Schema::table($tableName, function (Blueprint $table) use ($tableName, $indexName, $indexType) {
				if ($indexType == 'unique') {
					$table->dropUnique($indexName);
				} else {
					$table->dropIndex($indexName);
				}
			});
		} catch (Throwable $e) {
		}

		// If the custom naming index is still not drop, use raw SQL to drop it
		if (self::doesIndexExist($tableName, $indexName, $indexType)) {
			DBRawIndex::rawDropIndexIfExists($tableName, $indexName);
		}
	}

	/**
	 * @param string $tableName
	 * @param string|array $indexName
	 * @return bool
	 */
	public static function hasColumn(string $tableName, string|array $indexName): bool
	{
		$indexNamesList = !is_array($indexName) ? [$indexName] : $indexName;
		$indexNamesList = DBRawIndex::unwrapWithBacktick($indexNamesList);

		$existAll = true;
		foreach ($indexNamesList as $indexName) {
			if (!Schema::hasColumn($tableName, $indexName)) {
				$existAll = false;
				break;
			}
		}

		return $existAll;
	}
}
