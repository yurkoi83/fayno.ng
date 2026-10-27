<?php

use App\Exceptions\Custom\CustomException;
use App\Exceptions\Custom\InstalledFileWriteException;
use App\Helpers\Common\Arr;
use App\Http\Controllers\Installer\Steps\SiteInfoController;
use App\Models\AppVersion;
use Illuminate\Support\Facades\Schema;

/**
 * Create the "installed" file
 *
 * @throws CustomException
 */
function createTheInstalledFile(bool $stopOnException = false): void
{
	$filePath = storage_path('installed');
	$content = '';

	if (!file_exists($filePath)) {
		try {
			file_put_contents($filePath, $content);
		} catch (Throwable $e) {
		}
	}

	if (!file_exists($filePath)) {
		try {
			$fp = fopen($filePath, 'w');
			fwrite($fp, $content);
			fclose($fp);
		} catch (Throwable $e) {
			if ($stopOnException) {
				throw new CustomException($e->getMessage());
			}
		}
	}
}

/**
 * Read the "installed" file as a parsed associative array.
 *
 * Two on-disk formats are supported:
 *   - JSON: {"installed_at":"...","php_version":"...","purchase_code":"..."}
 *   - Legacy (pre-JSON installs): a bare purchase code as plain text.
 *
 * Returns an empty array when the file is missing, empty, or unreadable.
 */
function readInstalledFile(): array
{
	$filePath = storage_path('installed');
	if (!file_exists($filePath)) {
		return [];
	}

	$raw = trim((string)@file_get_contents($filePath));
	if ($raw === '') {
		return [];
	}

	$decoded = json_decode($raw, true);
	if (is_array($decoded)) {
		return $decoded;
	}

	// Legacy on-disk format: bare purchase code as plain text.
	return ['purchase_code' => $raw];
}

/**
 * Read the purchase code stored in the "installed" file.
 * Returns an empty string when the file is missing/empty/malformed.
 */
function readInstalledPurchaseCode(): string
{
	$data = readInstalledFile();

	return trim((string)($data['purchase_code'] ?? ''));
}

/**
 * Write the "installed" file as JSON.
 *
 * Preserves an existing `installed_at` across rewrites so it always reflects
 * the original installation time; refreshes `installed_app_version` /
 * `installed_php_version` to the runtime that just wrote the file. The
 * `installed_*` prefix makes it clear these are install-time snapshots and
 * not the running app's current version (the canonical source for that is
 * the `app_versions` DB table; see `getCurrentVersion()`).
 *
 * Returns true when the bytes were written, false otherwise (typically a
 * permission issue). Callers that need to escalate write failures should use
 * migrateInstalledFileIfNeeded() instead, which surfaces them as a typed
 * exception.
 */
function writeInstalledFile(): bool
{
	$existing = readInstalledFile();

	$payload = [
		'installed_at'          => $existing['installed_at'] ?? now('UTC')->toIso8601String(),
		'installed_app_version' => castToString(config('larapen.version.app')),
		'installed_php_version' => PHP_VERSION,
	];

	$bytes = @file_put_contents(
		storage_path('installed'),
		(string)json_encode($payload, JSON_UNESCAPED_SLASHES)
	);

	return $bytes !== false;
}

/**
 * Whether the "installed" file content matches the current canonical shape.
 *
 * Returns true when the file holds a parseable JSON object that uses the
 * current key names (`installed_app_version` / `installed_php_version`).
 * Files in the legacy bare-text format, or in the older JSON shape that
 * still carries the renamed-away `app_version` / `php_version` keys, are
 * reported as non-canonical so `migrateInstalledFileIfNeeded()` rewrites
 * them in place.
 *
 * A missing or empty file returns false (transient install state, not a
 * "non-JSON" file the migration should touch).
 */
function installedFileIsCanonical(): bool
{
	$filePath = storage_path('installed');
	if (!file_exists($filePath)) {
		return false;
	}

	$raw = trim((string)@file_get_contents($filePath));
	if ($raw === '') {
		return false;
	}

	$decoded = json_decode($raw, true);
	if (!is_array($decoded)) {
		return false;
	}

	// Old JSON shape (pre-rename): `app_version` / `php_version`.
	// Presence of either key signals a file that needs to be rewritten.
	if (array_key_exists('app_version', $decoded) || array_key_exists('php_version', $decoded)) {
		return false;
	}

	return true;
}

/**
 * Migrate the "installed" file to the canonical JSON shape when needed.
 *
 * Acts as the auto-upgrade trigger for existing customer installs:
 * legacy bare-text files and old/incomplete JSON shapes are rewritten in
 * place using whichever purchase code is recoverable (file's stored value
 * first, falling back to the configured one).
 *
 * No-op when:
 *   - the file does not yet exist (installation in progress; another path
 *     handles file creation);
 *   - the file is empty (transient post-install state, populated by verify);
 *   - the file is already in the canonical JSON shape.
 *
 * If the rewrite cannot land on disk (typically a permission issue), throws
 * InstalledFileWriteException so the operator is alerted instead of the gate
 * silently degrading.
 *
 * @throws InstalledFileWriteException
 */
function migrateInstalledFileIfNeeded(): void
{
	$filePath = storage_path('installed');
	if (!file_exists($filePath)) {
		return;
	}

	$raw = trim((string)@file_get_contents($filePath));
	if ($raw === '') {
		return;
	}

	if (installedFileIsCanonical()) {
		return;
	}

	$written = writeInstalledFile();

	if (!$written || !installedFileIsCanonical()) {
		throw new InstalledFileWriteException(
			sprintf(
				'Unable to update the installed file at "%s". '
				. 'Check that the web server user has write permission on this file.',
				$filePath
			)
		);
	}
}

/**
 * Check if the app's .env file exists
 */
function appEnvFileExists(): bool
{
	return file_exists(base_path('.env'));
}

/**
 * Check if the app's installation files exist
 */
function appInstallFilesExist(): bool
{
	// Check if the '.env' and 'storage/installed' files exist
	if (appEnvFileExists() && file_exists(storage_path('installed'))) {
		return true;
	}

	return false;
}

/**
 * Check if the app is installed
 */
function appIsInstalled(): bool
{
	// Check if the app's installation files exist
	return appInstallFilesExist();
}

/**
 * Check if the app is being installed or upgraded
 */
function appIsBeingInstalledOrUpgraded(): bool
{
	return appIsBeingInstalled() || appIsBeingUpgraded();
}

/**
 * Check if the app is being installed
 */
function appIsBeingInstalled(): bool
{
	return routeActionHas(getClassNamespaceName(SiteInfoController::class));
}

/**
 * Check if the app is being upgraded
 */
function appIsBeingUpgraded(): bool
{
	// Legacy upgrade route is gone (Web/Setup/Update was deleted in Phase 7).
	// The new upgrade flow uses UpdateManager + app_versions; nothing here
	// currently distinguishes "being upgraded" at the request layer.
	return false;
}

/**
 * Get the current installed version from the `app_versions` DB table.
 *
 * Single source of truth for the legacy compat checks
 * (`currentVersionIsLt()` / `currentVersionIsGte()` in `Listing`,
 * `User`, `Admin` middleware): the latest core row in `app_versions`,
 * recorded by `UpdateManager` at the end of every successful upgrade.
 *
 * Falls back to the on-disk code version when the table doesn't exist
 * yet (very early boot / fresh install pre-migration), so the compat
 * checks behave as if everything up to the current code is installed.
 *
 * Result is memoized per request to avoid hammering the DB on hot paths
 * like model cast resolution.
 */
function getCurrentVersion(): ?string
{
	static $memo = null;
	static $tableExists = null;

	if ($memo !== null) {
		return $memo;
	}

	try {
		if ($tableExists === null) {
			// Cross-request cache for the `app_versions` table-existence
			// check, so the `Schema::hasTable()` round-trip happens once
			// per cache cycle instead of once per request. AppVersion uses
			// `InvalidatesCache`, so the first Eloquent write on this model
			// (typically `recordVersion()` after a successful upgrade)
			// bumps the cache version and naturally evicts a stale `false`
			// cached before the migration ran.
			$tableExists = (bool)caching()->remember(
				AppVersion::class,
				['action' => 'has_table_app_versions'],
				fn (): bool => Schema::hasTable('app_versions'),
			);
		}

		if ($tableExists) {
			$installed = AppVersion::getLatestVersion('core');
			if (!empty($installed)) {
				return $memo = checkAndUseSemVer($installed);
			}
		}
	} catch (Throwable) {
		// DB or cache backend not available — remember that for the rest of
		// the request so subsequent calls don't pay the failed-handshake cost.
		$tableExists = false;
	}

	return $memo = checkAndUseSemVer(config('larapen.version.app'));
}

/**
 * Get the app's latest (code-on-disk) version, used by the
 * `currentVersionIs*` family as a fallback when no installed version
 * has been recorded yet.
 */
function getLatestVersion(): string
{
	return checkAndUseSemVer(config('larapen.version.app'));
}

/** Is the current app version equal to the provided version? */
function currentVersionIsEq(string $version): bool
{
	return version_compare(getCurrentVersion(), $version, '=');
}

/** Is the current app version less than the provided version? */
function currentVersionIsLt(string $version): bool
{
	return version_compare(getCurrentVersion(), $version, '<');
}

/** Is the current app version less than or equal to the provided version? */
function currentVersionIsLte(string $version): bool
{
	return version_compare(getCurrentVersion(), $version, '<=');
}

/** Is the current app version greater than the provided version? */
function currentVersionIsGt(string $version): bool
{
	return version_compare(getCurrentVersion(), $version, '>');
}

/** Is the current app version greater than or equal to the provided version? */
function currentVersionIsGte(string $version): bool
{
	return version_compare(getCurrentVersion(), $version, '>=');
}

/**
 * Check and use semver version num format
 */
function checkAndUseSemVer(?string $version): string
{
	$defaultSemver = '0.0.0';

	if (empty($version)) {
		return $defaultSemver;
	}

	$semver = null;

	if (empty($semver)) {
		$numPattern = '([0-9]+)';
		$hasValidFormat = preg_match('#^' . $numPattern . '\.' . $numPattern . '\.' . $numPattern . '$#', $version);
		$semver = $hasValidFormat ? $version : $semver;
	}
	if (empty($semver)) {
		$hasValidFormat = preg_match('#^' . $numPattern . '\.' . $numPattern . '$#', $version);
		$semver = $hasValidFormat ? $version . '.0' : $semver;
	}
	if (empty($semver)) {
		$hasValidFormat = preg_match('#^' . $numPattern . '$#', $version);
		$semver = $hasValidFormat ? $version . '.0.0' : $semver;
	}
	if (empty($semver)) {
		$semver = $defaultSemver;
	}

	return $semver;
}

function getRightPathsForCmd(
	string  $phpCmd,
	?string $schedule = '* * * * *',
	?string $return = '>> /dev/null 2>&1',
	bool    $withHint = true,
	bool    $wrapped = true
): ?string
{
	$splitCmd = explode(' ', $phpCmd, 2);

	// Get the script path
	$scriptName = trim(Arr::first($splitCmd));
	if ($scriptName == 'php') {
		$cmd = trim(Arr::last($splitCmd));
		$splitCmd = explode(' ', $cmd, 2);
		$scriptName = trim(Arr::first($splitCmd));
	}
	$scriptPath = base_path($scriptName);
	if (!file_exists($scriptPath)) {
		return null;
	}
	$scriptPath = relativeAppPath($scriptPath);

	// Get the command (everything that follows the script path).
	// When the input is a bare script with no extra arguments (e.g.
	// "utils/queue-work.php"), $splitCmd has a single element which IS the
	// script name — so Arr::last would echo the script back as args. Guard
	// against that and emit an empty $cmd in that case.
	$cmd = (count($splitCmd) >= 2) ? trim(Arr::last($splitCmd)) : '';
	$splitCmd = explode('>', $cmd, 2);
	$cmd = trim(Arr::first($splitCmd));

	// Get PHP bin path
	// TODO: surface a real PHP-CLI binary path + composer required PHP version
	// from App\Services\InstallerService\ChecksRequirements instead of these
	// inlined defaults (ported in when the legacy Web\Setup\…\PhpTrait stub
	// was retired).
	$phpBinaryDefaultPath = '/path/to/php';
	$phpBinaryPath = '';
	$requiredPhpVersion = PHP_VERSION;

	// Get hint when the PHP binary path cannot be found
	$hint = '';
	if (empty($phpBinaryPath)) {
		$phpBinaryPath = '/usr/bin/php';
		if ($withHint) {
			$hint = trans('messages.cron_jobs_hint', ['phpVersion' => $requiredPhpVersion]);
			if ($wrapped) {
				$alertBg = isAdminPanelRoute() ? 'alert-light-warning' : 'alert-warning';
				$hint .= '<div class="alert ' . $alertBg . '">';
				$hint .= $hint;
				$hint .= '</div>';
			}
		}
	}
	$phpBinaryPath = !isDemoDomain() ? $phpBinaryPath : $phpBinaryDefaultPath;

	// Schedule
	$schedule = is_null($schedule) ? '* * * * *' : $schedule;

	// Return
	$return = is_null($return) ? '>> /dev/null 2>&1' : $return;
	$return = (trim($schedule) == '') ? '' : $return;

	// Get cron job command
	$cron = $schedule . ' ' . $phpBinaryPath . ' ' . $scriptPath . ' ' . $cmd . ' ' . $return;
	$cron = trim($cron);

	// Build output
	if ($wrapped) {
		$out = '<div class="alert alert-light">';
		$out .= '<code>' . $cron . '</code>';
		$out .= '</div>';
	} else {
		$out = '<code>' . $cron . '</code><br><br>';
	}
	$out .= $hint;

	return $out;
}

function getHintForPhpCmd(): string
{
	// Get PHP required version
	// TODO: source the composer required PHP version from
	// App\Services\InstallerService\ChecksRequirements (ported in when the
	// legacy Web\Setup\…\PhpTrait stub was retired).
	$requiredPhpVersion = PHP_VERSION;

	// Get hint for a PHP binary path
	$alertBg = isAdminPanelRoute() ? 'alert-light-warning' : 'alert-warning';
	$hint = '<div class="alert ' . $alertBg . '">';
	$hint .= trans('messages.cron_jobs_hint', ['phpVersion' => $requiredPhpVersion]);
	$hint .= '</div>';

	return $hint;
}

function getAppMainPrefix(): string
{
	$itemPrefix = config('larapen.core.item.prefix');
	$generatedPrefix = generateAppMainPrefix();
	$itemPrefix = castToString($itemPrefix, $generatedPrefix);

	return !empty($itemPrefix) ? $itemPrefix : $generatedPrefix;
}

/**
 * Generate a prefix for the application consisting of a random three-character alphabetical string followed by an underscore.
 */
function generateAppMainPrefix(): string
{
	return generateRandomString(length: 3, type: 'alpha') . '_';
}
