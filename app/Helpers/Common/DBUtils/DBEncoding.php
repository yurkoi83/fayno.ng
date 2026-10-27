<?php

namespace App\Helpers\Common\DBUtils;

use App\Helpers\Common\DBUtils;
use App\Helpers\Common\DotenvEditor;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;
use Throwable;

class DBEncoding
{
	/**
	 * Find the charset & collation to set in the /.env file
	 *
	 * In MySQL, DEFAULT_COLLATION_NAME, collation_server, collation_connection,
	 * and collation_database represent collation settings that influence text handling in different contexts.
	 * Here's how each one affects a Laravel application:
	 *
	 * 1. DEFAULT_COLLATION_NAME
	 * - Description: This is the default collation associated with a character set in MySQL.
	 *   When you specify a character set without explicitly setting a collation, it defaults to this collation.
	 * - Effect in Laravel: If you set only the charset (e.g., utf8mb4) in Laravel's config/database.php and omit collation,
	 *   MySQL applies DEFAULT_COLLATION_NAME for that character set (e.g., utf8mb4_general_ci for utf8mb4).
	 * - Scope: It applies to any new database or table where only the character set is defined, without an explicit collation.
	 *
	 * 2. collation_server
	 * - Description: This is the default collation for the entire MySQL server instance.
	 * - Effect in Laravel: If you create a database in Laravel without specifying a collation,
	 *   it might inherit collation_server as its default collation, depending on the MySQL setup.
	 *   However, in Laravel, you typically define collation settings directly, so collation_server is less impactful.
	 * - Scope: It applies when creating a new database without an explicit collation, or as a fallback at the server level.
	 *
	 * 3. collation_connection
	 * - Description: This is the collation used for the current MySQL connection.
	 *   It determines how strings are compared and sorted within a specific session.
	 * - Effect in Laravel: Each time Laravel establishes a connection to the database,
	 *   it uses the collation specified in config/database.php (under the mysql connection).
	 *   If collation is specified, it overrides collation_connection to ensure consistency within the Laravel application.
	 * - Scope: It influences sorting and comparison operations for that particular session or connection.
	 *
	 * 4. collation_database
	 * - Description: This is the collation for the currently selected database in MySQL.
	 *   When tables or columns are created without an explicit collation, they inherit this database-level collation.
	 * - Effect in Laravel: When collation is set in config/database.php (e.g., utf8mb4_unicode_ci),
	 *   it typically maps to collation_database, ensuring all tables and columns default to this collation unless otherwise specified.
	 * - Scope: It applies to tables or columns within the database unless explicitly overridden at the table or column level.
	 *
	 * @param \PDO|null $pdo
	 * @return array
	 */
	public static function findConnectionCharsetAndCollation(?PDO $pdo = null): array
	{
		// Get default charset & collation
		$defaultCharset = config('larapen.core.database.encoding.default.charset', 'utf8mb4');
		$defaultCollation = config('larapen.core.database.encoding.default.collation', 'utf8mb4_unicode_ci');

		// Get the first recommended charset & collation that is available on the server
		$recommendedCharsetAndCollation = self::getFirstValidRecommendedCharsetAndCollation($pdo);
		if (!empty($recommendedCharsetAndCollation)) {
			$defaultCharset = $recommendedCharsetAndCollation['charset'] ?? $defaultCharset;
			$defaultCollation = $recommendedCharsetAndCollation['collation'] ?? $defaultCollation;
		}

		// Get the database charset & collation
		$charsetAndCollation = self::getDatabaseCharsetAndCollation($pdo);
		$charsetAndCollation = self::validateCharsetAndCollation($charsetAndCollation);

		// (If a valid charset and collation is not found)
		// Get the server charset & collation
		if (empty($charsetAndCollation)) {
			$charsetAndCollation = self::getServerCharsetAndCollation($pdo);
			$charsetAndCollation = self::validateCharsetAndCollation($charsetAndCollation);
		}

		if (empty($charsetAndCollation)) {
			$charsetAndCollation = [
				'charset'   => $defaultCharset,
				'collation' => $defaultCollation,
			];
		}

		return $charsetAndCollation;
	}

	/**
	 * Get the first recommended charset & collation that is available on the server
	 * Note: The 'recommended' list need to be ordered by from the most recommended to the less recommended
	 *
	 * @param \PDO|null $pdo
	 * @return array|null
	 */
	public static function getFirstValidRecommendedCharsetAndCollation(?PDO $pdo = null): ?array
	{
		$recommendedEncodings = (array)config('larapen.core.database.encoding.recommended');
		if (empty($recommendedEncodings)) return null;

		try {
			if (empty($pdo)) {
				$pdo = DB::connection()->getPdo();
			}

			foreach ($recommendedEncodings as $charset => $collations) {
				// Check if the charset is valid
				if (self::isValidCharset($charset, $pdo)) {
					// Check for the first valid collation in this charset
					foreach ($collations as $collation) {
						if (self::isValidCollation($collation, $charset, $pdo)) {
							// Return the first valid charset and collation
							return [
								'charset'   => $charset,
								'collation' => $collation,
							];
						}
					}
				}
			}
		} catch (Throwable $e) {
		}

		return null;
	}

	/**
	 * @param array $charsetAndCollation
	 * @param bool $fallback
	 * @return array
	 */
	public static function validateCharsetAndCollation(array $charsetAndCollation = [], bool $fallback = false): array
	{
		$charset = $charsetAndCollation['charset'] ?? '';
		$collation = $charsetAndCollation['collation'] ?? '';

		$defaultCharset = config('larapen.core.database.encoding.default.charset', 'utf8mb4');
		$defaultCollation = config('larapen.core.database.encoding.default.collation', 'utf8mb4_unicode_ci');

		if ($fallback) {
			if (empty($charset) || empty($collation)) {
				$charset = $defaultCharset;
				$collation = $defaultCollation;
			}
		}

		$recommendedEncodings = (array)config('larapen.core.database.encoding.recommended');
		if (array_key_exists($charset, $recommendedEncodings)) {
			$recommendedCollations = (array)$recommendedEncodings[$charset];
			if (!empty($recommendedCollations)) {
				if (!in_array($collation, $recommendedCollations)) {
					$collation = reset($recommendedCollations);
				}
			} else {
				$charset = null;
				$collation = null;
			}
		} else {
			$charset = null;
			$collation = null;
		}

		if (!empty($charset) && !empty($collation)) {
			if (!str_starts_with($collation, $charset)) {
				$charset = null;
				$collation = null;
			}
		}

		if ($fallback) {
			if (empty($charset) || empty($collation)) {
				$charset = $defaultCharset;
				$collation = $defaultCollation;
			}
		}

		if (empty($charset) || empty($collation)) {
			return [];
		}

		return [
			'charset'   => $charset,
			'collation' => $collation,
		];
	}

	/**
	 * Get the server's charset & collation using PDO
	 *
	 * @param \PDO|null $pdo
	 * @return array
	 */
	public static function getServerCharsetAndCollation(?PDO $pdo = null): array
	{
		$charsetAndCollation = [];

		try {
			if (empty($pdo)) {
				if (!appIsInstalled()) return [];
				$pdo = DB::connection()->getPdo();
			}

			// Query to get the server's default charset and collation
			$sql = "SELECT @@character_set_server AS charset, @@collation_server AS collation";
			$query = $pdo->query($sql);

			// Fetch the result as an associative array
			$charsetAndCollation = $query->fetch(PDO::FETCH_ASSOC);

			if (empty($charsetAndCollation['charset'])) {
				$charsetSql = "SHOW VARIABLES LIKE 'character_set_server'";
				$charset = $pdo->query($charsetSql)->fetch(PDO::FETCH_ASSOC);
				$charsetAndCollation['charset'] = $charset['Value'] ?? null;
			}

			if (empty($charsetAndCollation['collation'])) {
				$collationSql = "SHOW VARIABLES LIKE 'collation_server'";
				$collation = $pdo->query($collationSql)->fetch(PDO::FETCH_ASSOC);
				$charsetAndCollation['collation'] = $collation['Value'] ?? null;
			}

			if (empty($charsetAndCollation['charset']) || empty($charsetAndCollation['collation'])) {
				return [];
			}
		} catch (PDOException $e) {
		}

		return $charsetAndCollation;
	}

	/**
	 * Get the database's charset & collation using PDO
	 *
	 * @param \PDO|null $pdo
	 * @return array
	 */
	public static function getDatabaseCharsetAndCollation(?PDO $pdo = null): array
	{
		$charsetAndCollation = [];

		try {
			if (empty($pdo)) {
				if (!appIsInstalled()) return [];
				$pdo = DB::connection()->getPdo();
			}

			// Query to get the selected database default charset and collation
			$sql = "SELECT @@character_set_database AS charset, @@collation_database AS collation";
			$query = $pdo->query($sql);

			// Fetch the result as an associative array
			$charsetAndCollation = $query->fetch(PDO::FETCH_ASSOC);

			if (empty($charsetAndCollation['charset'])) {
				$charsetSql = "SHOW VARIABLES LIKE 'character_set_database'";
				$charset = $pdo->query($charsetSql)->fetch(PDO::FETCH_ASSOC);
				$charsetAndCollation['charset'] = $charset['Value'] ?? null;
			}

			if (empty($charsetAndCollation['collation'])) {
				$collationSql = "SHOW VARIABLES LIKE 'collation_database'";
				$collation = $pdo->query($collationSql)->fetch(PDO::FETCH_ASSOC);
				$charsetAndCollation['collation'] = $collation['Value'] ?? null;
			}

			if (empty($charsetAndCollation['charset']) || empty($charsetAndCollation['collation'])) {
				return [];
			}
		} catch (PDOException $e) {
		}

		return $charsetAndCollation;
	}

	/**
	 * Get the connection's charset & collation using PDO
	 * Note: Can be changed in the Laravel's /.env file
	 *
	 * @param \PDO|null $pdo
	 * @return array
	 */
	public static function getConnectionCharsetAndCollation(?PDO $pdo = null): array
	{
		$charsetAndCollation = [];

		try {
			if (empty($pdo)) {
				if (!appIsInstalled()) return [];
				$pdo = DB::connection()->getPdo();
			}

			// Query to get the connection's default charset and collation
			$sql = "SELECT @@character_set_connection AS charset, @@collation_connection AS collation";
			$query = $pdo->query($sql);

			// Fetch the result as an associative array
			$charsetAndCollation = $query->fetch(PDO::FETCH_ASSOC);

			if (empty($charsetAndCollation['charset'])) {
				$charsetSql = "SHOW VARIABLES LIKE 'character_set_connection'";
				$charset = $pdo->query($charsetSql)->fetch(PDO::FETCH_ASSOC);
				$charsetAndCollation['charset'] = $charset['Value'] ?? null;
			}

			if (empty($charsetAndCollation['collation'])) {
				$collationSql = "SHOW VARIABLES LIKE 'collation_connection'";
				$collation = $pdo->query($collationSql)->fetch(PDO::FETCH_ASSOC);
				$charsetAndCollation['collation'] = $collation['Value'] ?? null;
			}

			if (empty($charsetAndCollation['charset']) || empty($charsetAndCollation['collation'])) {
				return [];
			}
		} catch (PDOException $e) {
		}

		return $charsetAndCollation;
	}

	/**
	 * Get a specific table's default charset & collation using PDO
	 *
	 * Reads `information_schema` for the given RAW (already prefixed) table name
	 * in the currently selected database. Used to align a freshly created table's
	 * collation with the rest of the schema — e.g., the v19 `alerts` table, whose
	 * `country_code` column is joined against the legacy `countries.code` column.
	 * When the two tables carry different collations (an `utf8mb4_0900_ai_ci`
	 * table created on a MySQL 8 server whose pre-existing tables are
	 * `utf8mb4_unicode_ci`), the join throws MySQL error 1267
	 * ("Illegal mix of collations").
	 *
	 * Returns an empty array on non-MySQL drivers (no `information_schema`),
	 * when the table is absent, or on any PDO error.
	 *
	 * @param string $table RAW table name (with prefix, e.g. `lc_alerts`)
	 * @param \PDO|null $pdo
	 * @return array
	 */
	public static function getTableCharsetAndCollation(string $table, ?PDO $pdo = null): array
	{
		try {
			if (empty($pdo)) {
				if (!appIsInstalled()) return [];
				$pdo = DB::connection()->getPdo();
			}

			// A table's collation lives on `information_schema.TABLES`; its charset is
			// derived by joining the collation back to the applicability table (each
			// collation maps to exactly one charset).
			$sql = 'SELECT ccsa.CHARACTER_SET_NAME AS charset, t.TABLE_COLLATION AS collation FROM information_schema.TABLES t JOIN information_schema.COLLATION_CHARACTER_SET_APPLICABILITY ccsa ON ccsa.COLLATION_NAME = t.TABLE_COLLATION WHERE t.TABLE_SCHEMA = DATABASE() AND t.TABLE_NAME = :table LIMIT 1';
			$query = $pdo->prepare($sql);
			$query->execute(['table' => $table]);
			$result = $query->fetch(PDO::FETCH_ASSOC);

			$charset = $result['charset'] ?? null;
			$collation = $result['collation'] ?? null;

			if (empty($charset) || empty($collation)) {
				return [];
			}

			return [
				'charset'   => $charset,
				'collation' => $collation,
			];
		} catch (Throwable $e) {
		}

		return [];
	}

	/**
	 * Get the current /.env file's charset & collation
	 *
	 * @return array
	 */
	public static function getEnvCharsetAndCollation(): array
	{
		return [
			'charset'   => DBUtils::getDatabaseConnectionInfo('charset'),
			'collation' => DBUtils::getDatabaseConnectionInfo('collation'),
		];
	}

	/**
	 * @param \PDO|null $pdo
	 * @param bool $alterDatabase
	 * @return void
	 */
	public static function tryToFixConnectionCharsetAndCollation(?PDO $pdo = null, bool $alterDatabase = true): void
	{
		$isCharsetNeedToBeUpdated = false;

		// Default Charset & Collation
		$charset = config('larapen.core.database.encoding.default.charset', 'utf8mb4');
		$collation = config('larapen.core.database.encoding.default.collation', 'utf8mb4_unicode_ci');

		try {
			if (empty($pdo)) {
				$pdo = DB::connection()->getPdo();
			}

			// Find the charset & collation to set in the /.env file
			$charsetAndCollation = self::findConnectionCharsetAndCollation($pdo);
			$charset = $charsetAndCollation['charset'] ?? $charset;
			$collation = $charsetAndCollation['collation'] ?? $collation;

			// Get the current /.env file's charset & collation
			$envCharsetAndCollation = self::getEnvCharsetAndCollation();
			$envCharset = $envCharsetAndCollation['charset'] ?? null;
			$envCollation = $envCharsetAndCollation['collation'] ?? null;

			$isCharsetNeedToBeUpdated = ($charset != $envCharset || $collation != $envCollation);
		} catch (Throwable $e) {
		}

		if (!$isCharsetNeedToBeUpdated) return;

		// Update the database connection encoding in the /.env file
		try {
			$needToBeSaved = false;
			if (DotenvEditor::keyExists('DB_CHARSET')) {
				DotenvEditor::setKey('DB_CHARSET', $charset);
				$needToBeSaved = true;
			}
			if (DotenvEditor::keyExists('DB_COLLATION')) {
				DotenvEditor::setKey('DB_COLLATION', $collation);
				$needToBeSaved = true;
			}
			if ($needToBeSaved) {
				DotenvEditor::save();
			}
		} catch (Throwable $e) {
		}

		if (!$alterDatabase) return;

		// Update the database encoding
		try {
			// Run a query to get the current database name
			$databaseName = DBUtils::getRawDatabaseName($pdo);
			if (!empty($databaseName)) {
				// SQL query to update the database's charset & collation
				$sql = "ALTER DATABASE `$databaseName` CHARACTER SET $charset COLLATE $collation";

				// Perform the Query
				$pdo->exec($sql);
			}
		} catch (PDOException $e) {
		}
	}

	/**
	 * Check if the charset is valid
	 *
	 * @param string $charset
	 * @param \PDO|null $pdo
	 * @return bool
	 */
	public static function isValidCharset(string $charset, ?PDO $pdo = null): bool
	{
		try {
			if (empty($pdo)) {
				$pdo = DB::connection()->getPdo();
			}

			$sql = 'SELECT CHARACTER_SET_NAME FROM information_schema.CHARACTER_SETS WHERE CHARACTER_SET_NAME = :charset';
			$query = $pdo->prepare($sql);
			$query->execute(['charset' => $charset]);

			return !empty($query->fetchColumn());
		} catch (Throwable $e) {
		}

		return false;
	}

	/**
	 * Check if the collation is valid, or
	 * Check for the valid collation in a charset
	 *
	 * @param string $collation
	 * @param string|null $charset
	 * @param \PDO|null $pdo
	 * @return bool
	 */
	public static function isValidCollation(string $collation, ?string $charset = null, ?PDO $pdo = null): bool
	{
		try {
			if (empty($pdo)) {
				$pdo = DB::connection()->getPdo();
			}

			$sql = 'SELECT COLLATION_NAME FROM information_schema.COLLATIONS WHERE COLLATION_NAME = :collation';
			$sql .= !empty($charset) ? ' AND CHARACTER_SET_NAME = :charset' : '';
			$query = $pdo->prepare($sql);
			$query->execute(['collation' => $collation, 'charset' => $charset]);
			$isValidCollation = $query->fetchColumn();

			return !empty($isValidCollation);
		} catch (Throwable $e) {
		}

		return false;
	}

	/**
	 * @param \PDO|null $pdo
	 * @return bool
	 */
	public static function isValidCharsetAndCollation(?PDO $pdo = null): bool
	{
		try {
			if (empty($pdo)) {
				$pdo = DB::connection()->getPdo();
			}

			// Get all charset variables
			$sql = "SHOW VARIABLES LIKE 'character_set%'";
			$query = $pdo->query($sql);
			$charsetVars = $query->fetchAll(PDO::FETCH_ASSOC);
			if (!empty($charsetVars)) {
				$charsetVars = collect($charsetVars)->pluck('Value', 'Variable_name')->toArray();
			}

			// Get all collation variables
			$sql = "SHOW VARIABLES LIKE 'collation%'";
			$query = $pdo->query($sql);
			$collationVars = $query->fetchAll(PDO::FETCH_ASSOC);
			if (!empty($collationVars)) {
				$collationVars = collect($collationVars)->pluck('Value', 'Variable_name')->toArray();
			}

			// Get the current /.env file's charset & collation
			$envCharsetAndCollation = self::getEnvCharsetAndCollation();
			$envCharset = $envCharsetAndCollation['charset'] ?? null;
			$envCollation = $envCharsetAndCollation['collation'] ?? null;

			if (
				isset(
					$charsetVars['character_set_database'],
					$charsetVars['character_set_connection'],
					$collationVars['collation_database'],
					$collationVars['collation_connection']
				)
			) {
				$isValidCharset = (
					$charsetVars['character_set_database'] == $charsetVars['character_set_connection']
					&& $charsetVars['character_set_connection'] == $envCharset
				);

				if ($isValidCharset) {
					$isValidCollation = (
						str_starts_with($collationVars['collation_database'], $envCharset)
						&& str_starts_with($collationVars['collation_connection'], $envCharset)
					);
				} else {
					$isValidCollation = (
						$collationVars['collation_database'] == $collationVars['collation_connection']
						&& $collationVars['collation_connection'] == $envCollation
					);
				}

				return $isValidCharset && $isValidCollation;
			}
		} catch (Throwable $e) {
			return false;
		}

		return false;
	}
}
