<?php

namespace App\Helpers\Common\DBUtils;

use App\Helpers\Common\DBUtils;
use Illuminate\Support\Facades\DB;
use PDO;
use Throwable;

class DBFunction
{
	/**
	 * Check If a MySQL/MariaDB custom function exists
	 *
	 * Note: In MySQL and MariaDB, the information_schema.ROUTINES table
	 * only lists stored procedures and user-defined functions,
	 * not native (built-in) functions like ST_Distance_Sphere, etc.
	 *
	 * @param string|null $name
	 * @param \PDO|null $pdo
	 * @return bool
	 */
	public static function checkIfFunctionExists(?string $name, ?PDO $pdo = null): bool
	{
		if (empty($pdo)) {
			$pdo = DB::connection()->getPdo();
		}

		// Get the app's database name
		$databaseName = DBUtils::getRawDatabaseName($pdo);

		$exists = false;

		// Check with method #1
		try {
			$sql = 'SELECT COUNT(*) as function_exists
					FROM information_schema.ROUTINES
					WHERE ROUTINE_SCHEMA = :databaseName
						AND ROUTINE_NAME = :functionName
						AND ROUTINE_TYPE = "FUNCTION"';
			$query = $pdo->prepare($sql);
			$query->execute(['databaseName' => $databaseName, 'functionName' => $name]);
			$entry = $query->fetch(PDO::FETCH_OBJ);

			$exists = ($entry->function_exists > 0);
		} catch (Throwable $e) {
		}

		// Check with method #2
		if (!$exists) {
			try {
				$sql = 'SELECT ROUTINE_NAME
						FROM information_schema.ROUTINES
						WHERE ROUTINE_SCHEMA = :databaseName AND ROUTINE_TYPE = "FUNCTION"';
				$query = $pdo->prepare($sql);
				$query->execute(['databaseName' => $databaseName]);
				$entries = $query->fetchAll(PDO::FETCH_OBJ);

				$entries = collect($entries)->whereStrict('ROUTINE_NAME', $name);
				$exists = !$entries->isEmpty();
			} catch (Throwable $e) {
			}
		}

		// Check with method #3
		if (!$exists) {
			try {
				$sql = 'SHOW FUNCTION STATUS;';
				$query = $pdo->query($sql);
				$entries = $query->fetchAll(PDO::FETCH_OBJ);
				$entries = collect($entries)->whereStrict('Db', $databaseName)->whereStrict('Name', $name);

				$exists = $entries->isNotEmpty();
			} catch (Throwable $e) {
			}
		}

		return $exists;
	}

	/**
	 * Create a MySQL/MariaDB custom function
	 *
	 * @param string $sql
	 * @param \PDO|null $pdo
	 * @return bool
	 */
	public static function createFunction(string $sql, ?PDO $pdo = null): bool
	{
		try {

			if (empty($pdo)) {
				$pdo = DB::connection()->getPdo();
			}

			// Drop the function, If exists
			$name = self::extractFunctionName($sql);
			if (!empty($name)) {
				self::dropFunctionIfExists($name);
			}

			// Create the function
			$pdo->exec($sql);

		} catch (Throwable $e) {
			return false;
		}

		return true;
	}

	/**
	 * Drop a MySQL/MariaDB custom function, If exists
	 *
	 * @param string $name
	 * @param \PDO|null $pdo
	 * @return bool
	 */
	public static function dropFunctionIfExists(string $name, ?PDO $pdo = null): bool
	{
		try {

			if (empty($pdo)) {
				$pdo = DB::connection()->getPdo();
			}

			// Drop the function, If exists
			$sql = 'DROP FUNCTION IF EXISTS `' . $name . '`;';
			$pdo->exec($sql);

		} catch (Throwable $e) {
			return false;
		}

		return true;
	}

	/**
	 * Extract a MySQL function's name from SQL code
	 *
	 * @param string $sql
	 * @return string|null
	 */
	private static function extractFunctionName(string $sql): ?string
	{
		$matches = [];
		preg_match('#FUNCTION([^(]+)\(#i', $sql, $matches);

		return !empty($matches[1]) ? trim($matches[1]) : null;
	}
}
