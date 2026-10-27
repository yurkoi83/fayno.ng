<?php

namespace App\Helpers\Common;

use App\Exceptions\Custom\CustomException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use PDOException;
use Throwable;

class DBUtils
{
	/**
	 * Get PDO Connexion
	 *
	 * @param array|null $config
	 * @return \PDO
	 * @throws \App\Exceptions\Custom\CustomException
	 */
	public static function getPdoConnection(?array $config = []): PDO
	{
		// Retrieve Database Parameters from the /.env file
		// If they are not set during the function call.
		if (empty($config)) {
			$config = self::getDatabaseConnectionInfo();
		}

		// Retrieve database & its server parameters
		$driver = $config['driver'] ?? 'mysql';
		$host = $config['host'] ?? '';
		$port = $config['port'] ?? '';
		$database = $config['database'] ?? '';
		$username = $config['username'] ?? '';
		$password = $config['password'] ?? '';
		$socket = $config['socket'] ?? '';

		try {
			$charset = $config['charset'] ?? null;
			$options = $config['options'] ?? [
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
				PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_EMULATE_PREPARES   => true,
				PDO::ATTR_CURSOR             => PDO::CURSOR_FWDONLY,
			];

			// Build the DSN based on the driver
			$dsn = match ($driver) {
				'sqlite' => 'sqlite:' . $database,
				'pgsql'  => sprintf(
					'pgsql:host=%s;port=%s;dbname=%s%s',
					$host, $port, $database,
					!empty($charset) ? ';options=\'--client_encoding=' . $charset . '\'' : ''
				),
				default  => sprintf(
					'%s:%s;dbname=%s%s',
					$driver,
					!empty($socket) ? 'unix_socket=' . $socket : 'host=' . $host . ';port=' . $port,
					$database,
					!empty($charset) ? ';charset=' . $charset : ''
				),
			};

			// Connect to the database server
			if ($driver === 'sqlite') {
				return new PDO($dsn, options: $options);
			}

			return new PDO($dsn, $username, $password, $options);

		} catch (PDOException $e) {
			$errorMessage = trans('messages.database_pdo_connection_failed');
			$exceptionMessage = $e->getMessage();
		} catch (Throwable $e) {
			$errorMessage = trans('messages.database_connection_failed');
			$exceptionMessage = $e->getMessage();
		}

		$errorMessage ??= '';
		if (!empty($exceptionMessage)) {
			$exceptionMessageFormat = ' ERROR: <span class="fw-bold">%s</span>';
			$errorMessage .= sprintf($exceptionMessageFormat, $exceptionMessage);
		}

		throw new CustomException($errorMessage);
	}

	/**
	 * Database Connection Info
	 *
	 * @param string|null $param
	 * @return array|string|null
	 */
	public static function getDatabaseConnectionInfo(?string $param = null): array|string|null
	{
		$config = self::getLaravelDatabaseConfig();

		$databaseParams = $config['connections'][$config['default']];
		$databaseParams = is_array($databaseParams) ? $databaseParams : [];

		// Update some database parameters
		if (!empty($databaseParams)) {
			$databaseParams['port'] = (int)$databaseParams['port'];
			$databaseParams['socket'] = $databaseParams['unix_socket'];
			$databaseParams['options'] = [
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
				PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_EMULATE_PREPARES   => true,
				PDO::ATTR_CURSOR             => PDO::CURSOR_FWDONLY,
			];
		}

		if (!empty($param)) {
			$value = $databaseParams[$param] ?? null;

			return castToStringOrNull($value);
		}

		return $databaseParams;
	}

	/**
	 * @return array
	 */
	public static function getLaravelDatabaseConfig(): array
	{
		$path = __DIR__ . '/../../../config/database.php';
		if (!file_exists($path)) return [];

		return (array)include realpath($path);
	}

	/**
	 * Get the current database driver name.
	 */
	public static function getDriver(): string
	{
		$config = self::getLaravelDatabaseConfig();

		return $config['default'] ?? 'mysql';
	}

	/**
	 * Check if the current driver is MySQL or MariaDB.
	 */
	public static function isMysqlDriver(): bool
	{
		return in_array(self::getDriver(), ['mysql', 'mariadb']);
	}

	/**
	 * Check if the current driver is PostgreSQL.
	 */
	public static function isPgsqlDriver(): bool
	{
		return self::getDriver() === 'pgsql';
	}

	/**
	 * Check if the current driver is SQLite.
	 */
	public static function isSqliteDriver(): bool
	{
		return self::getDriver() === 'sqlite';
	}

	/**
	 * Close PDO Connexion
	 *
	 * @param $pdo
	 * @return void
	 */
	public static function closePdoConnection(&$pdo): void
	{
		$pdo = null;
	}

	/**
	 * @return string
	 */
	public static function getRawTablePrefix(): string
	{
		$config = self::getLaravelDatabaseConfig();
		$defaultConnection = $config['default'] ?? '';
		$defaultDatabase = $config['connections'][$defaultConnection] ?? [];
		$prefix = $defaultDatabase['prefix'] ?? '';

		return castToString($prefix);
	}

	/**
	 * Get the full table name by adding the DB prefix
	 *
	 * @param string $name
	 * @return string
	 */
	public static function table(string $name): string
	{
		return DB::getTablePrefix() . $name;
	}

	/**
	 * Get the full table name by adding the DB prefix
	 *
	 * @param string $name
	 * @return string
	 */
	public static function getRawTable(string $name): string
	{
		return self::getRawTablePrefix() . $name;
	}

	/**
	 * Quote a value with apostrophe to inject to an SQL statement
	 *
	 * @param $value
	 * @return false|string
	 */
	public static function quote($value): false|string
	{
		return DB::getPdo()->quote($value);
	}

	/**
	 * Get the selected database name
	 *
	 * @param \PDO $pdo
	 * @return string
	 */
	public static function getRawDatabaseName(PDO $pdo): string
	{
		$driver = self::getDriver();

		try {
			$databaseName = match ($driver) {
				'sqlite' => $pdo->query("PRAGMA database_list")->fetch(PDO::FETCH_ASSOC)['file'] ?? '',
				'pgsql'  => $pdo->query("SELECT current_database()")->fetchColumn(),
				default  => $pdo->query("SELECT DATABASE()")->fetchColumn(),
			};
		} catch (Throwable) {
			$databaseName = '';
		}

		return castToString($databaseName);
	}

	/**
	 * Check if a table exists in the current database (Using PDO)
	 *
	 * @param \PDO $pdo
	 * @param string $table
	 * @param string|null $tablesPrefix
	 * @return bool
	 */
	public static function rawTableExists(PDO $pdo, string $table, ?string $tablesPrefix = null): bool
	{
		// Try a select statement against the table
		// Run it in try/catch in case PDO is in ERRMODE_EXCEPTION.
		try {
			$fullTable = !empty($tablesPrefix) ? $tablesPrefix . $table : $table;
			$result = $pdo->query("SELECT 1 FROM {$fullTable} LIMIT 1");
		} catch (Throwable $e) {
			// We got an exception == table not found
			return false;
		}

		// Result is either boolean FALSE (no table found) or PDOStatement Object (table found)
		return $result !== false;
	}

	/**
	 * Get the app database's tables (By using PDO)
	 *
	 * @param \PDO $pdo
	 * @param string $database
	 * @param string|null $tablesPrefix
	 * @return array
	 * @throws \App\Exceptions\Custom\CustomException
	 */
	public static function getRawDatabaseTables(PDO $pdo, string $database, ?string $tablesPrefix = null): array
	{
		$tables = [];
		$driver = self::getDriver();

		try {
			if ($driver === 'sqlite') {
				$sql = "SELECT name FROM sqlite_master WHERE type='table'";
				$params = [];
				if (!empty($tablesPrefix)) {
					$sql .= ' AND name LIKE :prefix';
					$params[':prefix'] = $tablesPrefix . '%';
				}
				$stmt = $pdo->prepare($sql);
				$stmt->execute($params);
				$rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
				$tables = array_merge($tables, $rows);
			} else if ($driver === 'pgsql') {
				$sql = "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'";
				$params = [];
				if (!empty($tablesPrefix)) {
					$sql .= ' AND table_name LIKE :prefix';
					$params[':prefix'] = $tablesPrefix . '%';
				}
				$stmt = $pdo->prepare($sql);
				$stmt->execute($params);
				$rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
				$tables = array_merge($tables, $rows);
			} else {
				// MySQL / MariaDB
				$sql = 'SELECT GROUP_CONCAT(table_name) AS table_names
						FROM information_schema.tables
						WHERE table_schema = :database';
				$params = [':database' => $database];
				if (!empty($tablesPrefix)) {
					$sql .= ' AND table_name LIKE :prefix';
					$params[':prefix'] = $tablesPrefix . '%';
				}
				$stmt = $pdo->prepare($sql);
				$stmt->execute($params);
				$obj = $stmt->fetch();

				if (isset($obj->table_names)) {
					$tables = array_merge($tables, explode(',', $obj->table_names));
				}
			}
		} catch (Throwable $e) {
			throw new CustomException($e->getMessage());
		}

		return $tables;
	}

	/**
	 * Get the app database's tables (Using Laravel)
	 *
	 * @param string|null $tablesPrefix
	 * @param bool $withPrefix
	 * @return array
	 */
	public static function getDatabaseTables(?string $tablesPrefix = null, bool $withPrefix = true): array
	{
		$driver = self::getDriver();

		// SQLite doesn't support schema parameter
		if ($driver === 'sqlite') {
			$tables = collect(Schema::getTableListing());
		} else {
			$database = self::getDatabaseConnectionInfo('database');
			$tables = collect(Schema::getTableListing(schema: $database, schemaQualified: false));
		}

		// If a table prefix is provided,
		// Get only tables starting with the given prefix table
		if (!empty($tablesPrefix)) {
			$tables = $tables->filter(fn ($table) => str_starts_with($table, $tablesPrefix));
		}

		// If the tables' names without prefix are requested,
		// Remove the prefix from the name of the tables found
		if (!$withPrefix) {
			$tablesPrefix = empty($tablesPrefix) ? DB::getTablePrefix() : $tablesPrefix;
			$tables = $tables->map(fn ($item) => str($item)->replaceFirst($tablesPrefix, '')->toString());
		}

		return $tables->toArray();
	}

	/**
	 * Get SQL combined with binding values
	 *
	 * @param string $sql
	 * @param array $bindings
	 * @return string
	 */
	public static function getRealSql(string $sql, array $bindings = []): string
	{
		$sql = str_replace(['?'], ["'%s'"], $sql);

		return vsprintf($sql, $bindings);
	}

	/**
	 * Get the MySQL full version
	 *
	 * @param \PDO|null $pdo
	 * @return string
	 */
	public static function getMySqlFullVersion(?PDO $pdo = null): string
	{
		$version = '0';

		try {
			if (empty($pdo)) {
				$pdo = DB::connection()->getPdo();
			}

			if ($pdo instanceof PDO) {
				$driver = self::getDriver();
				$version = match ($driver) {
					'sqlite' => $pdo->query('SELECT sqlite_version()')->fetchColumn(),
					'pgsql'  => $pdo->query('SELECT version()')->fetchColumn(),
					default  => $pdo->query('SELECT VERSION()')->fetchColumn(),
				};
			}
		} catch (Throwable $e) {
		}

		return castToString($version);
	}

	/**
	 * Get the MySQL version
	 *
	 * @param \PDO|null $pdo
	 * @return string
	 */
	public static function getMySqlVersion(?PDO $pdo = null): string
	{
		$version = self::getMySqlFullVersion($pdo);

		$matches = [];
		preg_match('/^[\d.]+/', $version, $matches);

		return !empty($matches[0]) ? trim($matches[0]) : $version;
	}

	/**
	 * Check if the entered value is the MySQL minimal version
	 *
	 * @param string $min
	 * @return bool
	 */
	public static function isMySqlMinVersion(string $min): bool
	{
		// Get the MySQL version
		$version = self::getMySqlVersion();

		return (version_compare($version, $min) >= 0);
	}

	/**
	 * Check if the database is MariaDB
	 *
	 * @param \PDO|null $pdo
	 * @return bool
	 */
	public static function isMariaDB(?PDO $pdo = null): bool
	{
		$version = self::getMySqlFullVersion($pdo);

		return str_contains($version, 'MariaDB');
	}

	/**
	 * Get MySQL/MariaDB max connections and max user connections.
	 *
	 * @param \PDO|null $pdo
	 * @return array An associative array with 'max_connections' and 'max_user_connections' values.
	 */
	public static function getMysqlConnectionLimits(?PDO $pdo = null): array
	{
		try {
			if (empty($pdo)) {
				$pdo = DB::connection()->getPdo();
			}

			// Query to get max_connections and max_user_connections
			$query = "SHOW VARIABLES WHERE Variable_name IN ('max_connections', 'max_user_connections')";
			$stmt = $pdo->query($query);
			$variables = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

			// Return the values as an associative array
			return [
				'max_connections'      => $variables['max_connections'] ?? null,
				'max_user_connections' => $variables['max_user_connections'] ?? null,
			];
		} catch (PDOException $e) {
			return [];
		}
	}

	/**
	 * Import SQL File (per-statement)
	 *
	 * Replays the file one statement at a time, wrapped in a single transaction
	 * to avoid InnoDB's per-INSERT fsync. Failed statements are collected and
	 * reported together, then thrown as a single exception. For a faster, single
	 * multi-statement exec (no per-statement recovery), see self::bulkImportSqlFile().
	 *
	 * @param \PDO $pdo
	 * @param string $sqlFile
	 * @param string|null $tablePrefix
	 * @param string|null $InFilePath
	 * @return void
	 * @throws \App\Exceptions\Custom\CustomException
	 */
	public static function importSqlFile(PDO $pdo, string $sqlFile, ?string $tablePrefix = null, ?string $InFilePath = null): void
	{
		$content = self::prepareSqlFileContent($pdo, $sqlFile, $tablePrefix, $InFilePath);
		if ($content === null) {
			return;
		}

		// Per-statement, but wrapped in a transaction to avoid InnoDB fsync per INSERT.
		$startedTransaction = false;
		try {
			$startedTransaction = $pdo->beginTransaction();
		} catch (\Throwable) {
			// Some drivers / nested-transaction setups may refuse; we'll proceed without
		}

		$errorDetect = false;
		$errors = '';
		$tmpLine = '';

		foreach (preg_split('/\R/', $content) ?: [] as $line) {
			if ($line === '' || str_starts_with(ltrim($line), '--')) {
				continue;
			}

			$tmpLine .= $line . "\n";

			if (str_ends_with(rtrim($line), ';')) {
				try {
					$pdo->exec($tmpLine);
				} catch (PDOException $e) {
					$errors .= 'Error occurred in the file: ' . $sqlFile;
					$errors .= ' with the query: "' . $tmpLine . '" - Info: ' . $e->getMessage() . "\n";
					$errorDetect = true;
				}

				$tmpLine = '';
			}
		}

		if ($startedTransaction) {
			try {
				if ($pdo->inTransaction()) {
					$pdo->commit();
				}
			} catch (\Throwable) {
				// A DDL statement (e.g., ALTER TABLE) may have implicitly committed; ignore
			}
		}

		if ($errorDetect) {
			throw new CustomException($errors);
		}
	}

	/**
	 * Import SQL File (single multi-statement exec)
	 *
	 * Wraps the whole file in one transaction and runs it as a single
	 * multi-statement exec. Avoids InnoDB's per-INSERT fsync (the dominant cost
	 * on autocommit connections) but offers no per-statement recovery: the
	 * first error aborts the whole import.
	 *
	 * Note: DDL statements (CREATE/ALTER/DROP TABLE) implicitly commit in
	 * MySQL/MariaDB, so a mid-file failure cannot be fully rolled back — the file
	 * may be left partially applied. Use self::importSqlFile() when re-running a
	 * partially applied file must stay safe.
	 *
	 * @param \PDO $pdo
	 * @param string $sqlFile
	 * @param string|null $tablePrefix
	 * @param string|null $InFilePath
	 * @return void
	 * @throws \App\Exceptions\Custom\CustomException
	 */
	public static function bulkImportSqlFile(PDO $pdo, string $sqlFile, ?string $tablePrefix = null, ?string $InFilePath = null): void
	{
		$content = self::prepareSqlFileContent($pdo, $sqlFile, $tablePrefix, $InFilePath);
		if ($content === null) {
			return;
		}

		try {
			$pdo->beginTransaction();
			$pdo->exec($content);
			$pdo->commit();
		} catch (PDOException $e) {
			if ($pdo->inTransaction()) {
				try {
					$pdo->rollBack();
				} catch (\Throwable) {
				}
			}

			$errors = 'Error occurred in the file: ' . $sqlFile . ' - Info: ' . $e->getMessage() . "\n";
			throw new CustomException($errors);
		}
	}

	/**
	 * Read an SQL dump file and prepare its content for import.
	 *
	 * Enables LOCAL INFILE on the connection, resolves the table-prefix /
	 * infile-path placeholders, and strips MyISAM-only DDL hints (which are
	 * no-ops on InnoDB and would implicitly commit a wrapping transaction).
	 *
	 * @param \PDO $pdo
	 * @param string $sqlFile
	 * @param string|null $tablePrefix
	 * @param string|null $InFilePath
	 * @return string|null  The prepared SQL, or null when the file is missing/empty.
	 */
	private static function prepareSqlFileContent(PDO $pdo, string $sqlFile, ?string $tablePrefix = null, ?string $InFilePath = null): ?string
	{
		// Enable to LOAD LOCAL INFILE
		$pdo->setAttribute(PDO::MYSQL_ATTR_LOCAL_INFILE, true);

		$content = @file_get_contents($sqlFile);
		if ($content === false || $content === '') {
			return null;
		}

		// Resolve placeholders in one pass
		$content = strtr($content, [
			'<<prefix>>'      => (string)$tablePrefix,
			'<<InFilePath>>'  => (string)$InFilePath,
			'__PREFIX__'      => (string)$tablePrefix,
			'__INFILE_PATH__' => (string)$InFilePath,
		]);

		// Strip MyISAM-only DDL hints (DISABLE/ENABLE KEYS, LOCK/UNLOCK TABLES) — they're
		// no-ops on InnoDB, and they implicitly commit a wrapping transaction.
		$content = preg_replace(
			'#/\*!\d+\s+ALTER\s+TABLE[^*]*\*/\s*;\s*#i',
			'',
			$content
		) ?? $content;
		$content = preg_replace('#^\s*(LOCK|UNLOCK)\s+TABLES[^;]*;\s*#im', '', $content) ?? $content;

		return castToString($content);
	}

	/**
	 * Get the app's model files
	 *
	 * @return array
	 */
	public static function getAppModelFiles(): array
	{
		$modelFiles = [];
		try {
			// Get all files available in the "app/Models/" directory
			$modelDirPath = app_path('Models') . DIRECTORY_SEPARATOR;
			$files = array_filter(glob($modelDirPath . '*.php'), 'is_file');

			if (!empty($files)) {
				foreach ($files as $filePath) {
					$isModelFile = self::isModelFile($filePath);
					if (!$isModelFile) {
						continue;
					}

					$modelFiles[] = $filePath;
				}
			}
		} catch (Throwable $e) {
		}

		return $modelFiles;
	}

	/**
	 * Get the app's model classes
	 *
	 * @param bool $translatable
	 * @return array
	 */
	public static function getAppModelClasses(bool $translatable = false): array
	{
		// Get model files paths
		$files = self::getAppModelFiles();
		$files = collect($files);

		// Get class full name from path
		$pathToClass = function ($path) {
			$namespace = '\App\Models\\';
			$modelName = pathinfo(basename($path), PATHINFO_FILENAME);

			return $namespace . $modelName;
		};

		// Convert file paths to fully qualified class names
		// & Get valid model classes
		$classes = $files->map($pathToClass)->filter(fn ($class) => self::isModelClass($class));

		// Check if the model is translatable
		if ($translatable) {
			// Check if model is translatable
			$modelIsTranslatable = function ($modelClass) {
				try {
					$model = new $modelClass;

					return (method_exists($model, 'translationEnabledForModel') && $model->translationEnabledForModel());
				} catch (Throwable $e) {
					return false;
				}
			};

			// Get translatable model classes
			$classes = $classes->filter($modelIsTranslatable);
		}

		return $classes->toArray();
	}

	/**
	 * Check if a class is a model class
	 *
	 * @param string $modelClass
	 * @return bool
	 */
	public static function isModelClass(string $modelClass): bool
	{
		try {
			$model = new $modelClass;

			return $model instanceof Model;
		} catch (Throwable $e) {
			return false;
		}
	}

	/**
	 * Check if a file is a model
	 *
	 * @param string $fileFullPath
	 * @return bool
	 */
	public static function isModelFile(string $fileFullPath): bool
	{
		if (!file_exists($fileFullPath)) {
			return false;
		}

		if (!str_ends_with(strtolower($fileFullPath), '.php')) {
			return false;
		}

		// Check models that do not have a table propriety
		$modelsWithoutTableName = ['Permission', 'Role'];
		$modelName = pathinfo(basename($fileFullPath), PATHINFO_FILENAME);
		if (in_array($modelName, $modelsWithoutTableName)) {
			return true;
		}

		// Check models that have a table propriety
		$table = self::getModelTableName($fileFullPath);

		return !empty($table);
	}

	/**
	 * Get a Model table name by parsing its file
	 *
	 * @param string $fileFullPath
	 * @param string|null $tablesPrefix
	 * @return string|null
	 */
	public static function getModelTableName(string $fileFullPath, ?string $tablesPrefix = null): ?string
	{
		if (!file_exists($fileFullPath)) {
			return null;
		}

		if (!str_ends_with(strtolower($fileFullPath), '.php')) {
			return null;
		}

		$content = file_get_contents($fileFullPath);

		// Get the model table's name
		$matches = [];
		preg_match('#\$table[^=]*=[^\']*\'([^\']+)\';#i', $content, $matches);
		$table = !empty($matches[1]) ? $matches[1] : null;

		if (!empty($tablesPrefix) && !empty($table)) {
			$table = $tablesPrefix . $table;
		}

		return castToStringOrNull($table);
	}

	/**
	 * Convert table's columns from string to JSON
	 *
	 * @param string $tableName
	 * @param array $columns
	 * @param string $locale
	 * @return void
	 */
	public static function convertTranslatableDataToJson(string $tableName, array $columns, string $locale = 'en'): void
	{
		if (count($columns) <= 0) {
			return;
		}

		// Sanitize locale: only allow alphanumeric, hyphens, and underscores
		if (!preg_match('/^[a-zA-Z0-9_-]+$/', $locale)) {
			return;
		}

		// Get actual table columns to validate against
		$tableColumns = Schema::getColumnListing($tableName);

		$driver = self::getDriver();
		$quote = ($driver === 'pgsql') ? '"' : '`';

		foreach ($columns as $column) {
			// Validate column exists in table (prevents column name injection)
			if (!in_array($column, $tableColumns, true)) {
				continue;
			}

			$quotedColumn = $quote . str_replace([$quote, "\0"], '', $column) . $quote;

			$statement = match ($driver) {
				'pgsql'  => "'{\"" . $locale . "\":\"' || " . $quotedColumn . " || '\"}'",
				'sqlite' => "'{\"" . $locale . "\":\"' || " . $quotedColumn . " || '\"}'",
				default  => 'CONCAT("{\"' . $locale . '\":\"", ' . $quotedColumn . ', "\"}")',
			};

			DB::table($tableName)
				->where($column, 'NOT LIKE', '%{%')
				->where($column, 'NOT LIKE', '%}%')
				->update([
					$column => DB::raw($statement),
				]);
		}
	}
}
