<?php
/**
 * Raw SQL for the module, through the core's PDO connection (icms::$db)
 *
 * The IPF handlers keep using the legacy adapter they are given for their own
 * queries. Everything written by hand in this module goes through here,
 * with bound parameters instead of values glued into the SQL. Both adapters
 * share one PDO connection, so they see each other's writes at once.
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once dirname(__DIR__) . '/include/common.php';

class mod_imforum_Db {

	/**
	 * The PDO connection of the core
	 *
	 * icms::$db is empty when the site still runs the legacy mysql adapter,
	 * which PHP 8 does not have anymore.
	 *
	 * @throws RuntimeException
	 */
	public static function connection(): PDO {
		$connection = icms::$db;
		if (!$connection instanceof PDO) {
			throw new RuntimeException('imforum needs the PDO database connection: set XOOPS_DB_TYPE to pdo.mysql in mainfile.php.');
		}
		return $connection;
	}

	/**
	 * Full name of a table of this module: prefix, module and item
	 */
	public static function table(string $item): string {
		return XOOPS_DB_PREFIX . '_' . IMFORUM_DIRNAME . '_' . $item;
	}

	/**
	 * Placeholders for a list: "?,?,?"
	 *
	 * @param list<mixed> $values
	 */
	public static function marks(array $values): string {
		return implode(',', array_fill(0, max(1, count($values)), '?'));
	}

	/**
	 * Run a statement that changes data
	 *
	 * @param list<int|string|null> $params
	 * @return int|null rows changed, null when the statement failed
	 */
	public static function execute(string $sql, array $params = []): ?int {
		$statement = self::run($sql, $params);
		return $statement === null ? null : $statement->rowCount();
	}

	/**
	 * Run a statement that changes data and say whether it worked
	 *
	 * @param list<int|string|null> $params
	 */
	public static function ok(string $sql, array $params = []): bool {
		return self::execute($sql, $params) !== null;
	}

	/**
	 * All rows of a query, each as a list of values
	 *
	 * @param list<int|string|null> $params
	 * @return list<list<int|string|null>> the driver's own types, so cast what you use
	 */
	public static function rows(string $sql, array $params = []): array {
		$statement = self::run($sql, $params);
		return $statement === null ? [] : array_values($statement->fetchAll(PDO::FETCH_NUM));
	}

	/**
	 * The first row of a query, or an empty list
	 *
	 * @param list<int|string|null> $params
	 * @return list<int|string|null>
	 */
	public static function row(string $sql, array $params = []): array {
		return self::rows($sql, $params)[0] ?? [];
	}

	/**
	 * The first value of the first row, 0 when there is none
	 *
	 * @param list<int|string|null> $params
	 */
	public static function value(string $sql, array $params = []): int {
		return (int) (self::row($sql, $params)[0] ?? 0);
	}

	/**
	 * Integers go in as integers, so a LIMIT keeps working
	 *
	 * @param list<int|string|null> $params
	 */
	private static function run(string $sql, array $params): ?PDOStatement {
		$statement = self::connection()->prepare($sql);
		if ($statement === false) {
			return null;
		}
		foreach ($params as $i => $value) {
			$statement->bindValue($i + 1, $value, is_int($value) ? PDO::PARAM_INT : ($value === null ? PDO::PARAM_NULL : PDO::PARAM_STR));
		}
		return $statement->execute() ? $statement : null;
	}
}
