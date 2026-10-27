<?php

namespace App\Models\Builders\Classes;

use App\Helpers\Common\DBUtils;
use App\Models\Builders\Classes\Helpers\JsonHelper;
use App\Models\Builders\HasSafeWhere;
use Closure;

/*
 * Builder for translatable models ***
 * The create(), update(), find(), findOrFail(), findMany(), findBySlug(), and findBySlugOrFail() methods
 * for translatable models are implemented in the:
 * 'app/Http/Controllers/Web/Admin/Panel/Library/Traits/Models/SpatieTranslatable/HasTranslations.php' file
 */

class TranslationsBuilder extends GlobalBuilder
{
	use HasSafeWhere;

	public function where($column, $operator = null, $value = null, $boolean = 'and'): static
	{
		if ($column instanceof Closure) {
			return parent::where($column, $operator, $value, $boolean);
		}

		// Is it a translatable model? If so, check if the column is translatable
		// Model or column is not translatable
		$model = $this->model ?? null;
		if (!isTranslatableColumn($model, $column)) {
			return parent::where($column, $operator, $value, $boolean);
		}

		// Translatable model and column
		if (func_num_args() == 2 && empty($value)) {
			$value = $operator;
		}

		// Escaping Quote
		$value = str_replace(['\''], ['\\\''], $value);

		// JSON columns manipulation is only available in:
		// MySQL 5.7 or above & MariaDB 10.2.3 or above
		$jsonMethodsAreAvailable = (
			(!DBUtils::isMariaDB() && DBUtils::isMySqlMinVersion('5.7'))
			|| (DBUtils::isMariaDB() && DBUtils::isMySqlMinVersion('10.2.3'))
		);
		if ($jsonMethodsAreAvailable) {

			$localeChain = $this->translatableLocaleChain();

			return parent::where(function (self $query) use ($column, $localeChain, $value) {
				/*
				 * Get the DB collation to apply it to raw SQLs
				 * (Laravel seems to automatically apply it through non-raw queries)
				 *
				 * Important:
				 * To make accent-insensitive searches, we need to use an accent-insensitive collation
				 * MySQL provides collations that are accent-insensitive (ignoring accents and diacritics like ț, ț, á, etc.).
				 * For example, we can use the utf8mb4_unicode_ci or utf8mb4_general_ci collation,
				 * which treats diacritics and non-diacritics equivalently in comparisons.
				 */
				$isDiacriticsEnabled = (setting('listings_list_enable_diacritics') == '1');
				$collation = $isDiacriticsEnabled ? DBUtils::getDatabaseConnectionInfo('collation') : null;

				// Get the where's value (built once, reused for each locale in the chain)
				$value = 'LOWER(\'' . $value . '\')';
				// $value = 'BINARY ' . $value; // Enforce a case-sensitive comparison in MySQL
				$value = !empty($collation) ? $value . ' COLLATE ' . $collation : $value;

				/*
				 * Match the column against each locale in the fallback chain
				 * (current locale, then the configured DB fallback, then 'en' as a last resort)
				 * so a missing or misconfigured fallback locale cannot blank out the search.
				 */
				foreach ($localeChain as $index => $chainLocale) {
					// Get the where's column
					$jsonColumn = JsonHelper::jsonExtract($column, $chainLocale);
					$jsonColumn = 'LOWER(' . $jsonColumn . ')';
					// $jsonColumn = 'BINARY ' . $jsonColumn; // Enforce a case-sensitive comparison in MySQL
					$jsonColumn = !empty($collation) ? $jsonColumn . ' COLLATE ' . $collation : $jsonColumn;

					// Get the where's raw SQL
					$rawSql = $jsonColumn . ' LIKE ' . $value;

					// Fire the (or)where's raw query
					if ($index === 0) {
						$query->whereRaw($rawSql);
					} else {
						$query->orWhereRaw($rawSql);
					}
				}
			});

		} else {

			$value = str($value)->start('%')->toString();
			$value = str($value)->finish('%')->toString();

			return parent::where($column, 'LIKE', $value, $boolean);

		}
	}

	public function orWhere($column, $operator = null, $value = null): static
	{
		// Is it a translatable model? If so, check if the column is translatable
		// Model or column is not translatable
		$model = $this->model ?? null;
		if (!isTranslatableColumn($model, $column)) {
			return parent::orWhere($column, $operator, $value);
		}

		// Translatable model and column
		return parent::orWhere(fn (self $query) => $query->where($column, $operator, $value));
	}

	public function orderBy($column, $direction = 'asc', $locale = null): TranslationsBuilder|static
	{
		// Is it a translatable model? If so, check if the column is translatable
		// Model or column is not translatable
		$model = $this->model ?? null;
		if (!isTranslatableColumn($model, $column)) {
			return parent::orderBy($column, $direction);
		}

		// Translatable model and column
		$locale = $locale ?? app()->getLocale();
		$masterLocale = config('translatable.fallback_locale') ?? config('app.fallback_locale');

		$jsonMethodsAreAvailable = (
			(!DBUtils::isMariaDB() && DBUtils::isMySqlMinVersion('5.7'))
			|| (DBUtils::isMariaDB() && DBUtils::isMySqlMinVersion('10.2.3'))
		);
		if ($jsonMethodsAreAvailable) {

			/*
			 * Sort by each locale in the fallback chain (current locale, then the configured
			 * DB fallback, then 'en') so rows matched via a fallback locale still sort.
			 */
			foreach ($this->translatableLocaleChain($locale) as $chainLocale) {
				$jsonColumn = JsonHelper::jsonExtract($column, $chainLocale);
				$this->orderByRaw($jsonColumn . ' ' . $direction);
			}

		} else {

			/*
			 * Remove the first part of the column up to and including the first "$locale":"
			 * IMPORTANT: To prevent MySQL limitation, use '"en":' instead of '"en":"' that provides a wrong result.
			 * DEBUG: SELECT LOCATE('"en":', name) as nPos, SUBSTR(name, LOCATE('"en":', name)+6) as cName FROM lc_categories WHERE parent_id IS NULL;
			 */
			$subStr = '"' . $locale . '":';
			$subStrPos = 'LOCATE(\'' . $subStr . '\', ' . $column . ')';
			$jsonColumn = 'SUBSTR(' . $column . ', ' . $subStrPos . '+' . (strlen($subStr) + 1) . ')';
			$jsonColumn = 'IF(' . $subStrPos . ' > 0, ' . $jsonColumn . ', NULL)';
			// With COALESCE(), returns the first non-NULL value in a specified list of arguments (here 'zz')
			$jsonColumn = 'COALESCE(' . $jsonColumn . ', \'zz\')';
			$this->orderByRaw($jsonColumn . ' ' . $direction);

			if (!empty($masterLocale) && $locale != $masterLocale) {
				$subStr = '"' . $masterLocale . '":';
				$subStrPos = 'LOCATE(\'' . $subStr . '\', ' . $column . ')';
				$jsonColumn = 'SUBSTR(' . $column . ', ' . $subStrPos . '+' . (strlen($subStr) + 1) . ')';
				$jsonColumn = 'IF(' . $subStrPos . ' > 0, ' . $jsonColumn . ', ' . $column . ')';
				$this->orderByRaw($jsonColumn . ' ' . $direction);
			}

		}

		return $this;
	}

	/**
	 * Ordered, de-duplicated list of locales to match/sort a translatable column against.
	 *
	 * The current locale comes first, then the configured DB fallback locale
	 * (FALLBACK_LOCALE_FOR_DB), then 'en' as a guaranteed last resort. This prevents a
	 * missing or misconfigured fallback locale from blanking out translatable search when
	 * the column only holds values for a single language (e.g., English-only seed data).
	 *
	 * @return array<int, string>
	 */
	protected function translatableLocaleChain(?string $locale = null): array
	{
		$locale = $locale ?: app()->getLocale();
		$fallbackLocale = config('translatable.fallback_locale') ?? config('app.fallback_locale');

		return array_values(array_unique(array_filter([
			$locale,
			$fallbackLocale,
			'en',
		])));
	}
}
