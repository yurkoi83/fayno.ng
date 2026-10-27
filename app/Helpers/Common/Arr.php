<?php

namespace App\Helpers\Common;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use stdClass;
use Throwable;

class Arr extends \Illuminate\Support\Arr
{
	/**
	 * Convert the array into a query string.
	 * Replace & remove: httpBuildQuery()
	 *
	 * @param $array
	 * @return string
	 */
	public static function query($array): string
	{
		$query = parent::query($array);
		$query = str_replace(['%5B', '%5D'], ['[', ']'], $query);

		return castToString($query);
	}

	/**
	 * Sort multidimensional array by subarray key
	 *
	 * @param $array
	 * @param string $field
	 * @param string $order
	 * @param bool $keepIndex
	 * @return array|\Illuminate\Support\Collection|\stdClass
	 */
	public static function sortBy($array, string $field, string $order = 'asc', bool $keepIndex = true): array|Collection|stdClass
	{
		$isLaravelCollection = false;
		$isObject = false;

		if (is_object($array)) {
			if ($array instanceof Collection) {
				$array = $array->toArray();
				$isLaravelCollection = true;
			} else {
				$array = self::fromObject($array);
				$isObject = true;
			}
		}

		// If an array is not found
		if (!is_array($array)) return [];

		// If the array found is empty
		if (empty($array)) {
			return $isLaravelCollection ? self::toCollection([]) : ($isObject ? self::toObject([]) : []);
		}

		// Get sorting order
		$int = (strtolower($order) == 'desc') ? -1 : 1;

		// Sorting
		if ($keepIndex) {
			uasort($array, function ($a, $b) use ($field, $int) {
				if ($a[$field] == $b[$field]) {
					return 0;
				}

				return ($a[$field] < $b[$field]) ? -$int : $int;
			});
		} else {
			usort($array, function ($a, $b) use ($field, $int) {
				if ($a[$field] == $b[$field]) {
					return 0;
				}

				return ($a[$field] < $b[$field]) ? -$int : $int;
			});
		}

		return ($isLaravelCollection) ? self::toCollection($array) : (($isObject) ? self::toObject($array) : $array);
	}

	/**
	 * Sort multidimensional array by subarray key (Multi-bytes version)
	 * Need to be installed the PHP intl Extension
	 *
	 * @param $array
	 * @param string $field
	 * @param string $locale
	 * @param string $order
	 * @param bool $keepIndex
	 * @return array|\Illuminate\Support\Collection|\stdClass
	 */
	public static function mbSortBy(
		$array,
		string $field,
		string $locale = 'en_US',
		string $order = 'asc',
		bool $keepIndex = true
	): array|Collection|stdClass
	{
		$isLaravelCollection = false;
		$isObject = false;

		if (is_object($array)) {
			if ($array instanceof Collection) {
				$array = $array->toArray();
				$isLaravelCollection = true;
			} else {
				$array = self::fromObject($array);
				$isObject = true;
			}
		}

		// If an array is not found
		if (!is_array($array)) return [];

		// If the array found is empty
		if (empty($array)) {
			return $isLaravelCollection ? self::toCollection([]) : ($isObject ? self::toObject([]) : []);
		}

		// \Collator is available in the PHP intl Extension
		if (!(extension_loaded('intl') && class_exists('\Collator'))) {
			$array = $isLaravelCollection ? self::toCollection($array) : ($isObject ? self::toObject($array) : $array);

			return self::sortBy($array, $field, $order, $keepIndex);
		}

		try {
			$collator = \Collator::create($locale);
		} catch (Throwable $e) {
			$array = $isLaravelCollection ? self::toCollection($array) : ($isObject ? self::toObject($array) : $array);

			return self::sortBy($array, $field, $order, $keepIndex);
		}

		// Get sorting order
		$int = (strtolower($order) == 'desc') ? -1 : 1;

		// Sorting
		if ($keepIndex) {
			uasort($array, function ($a, $b) use ($collator, $field, $int) {
				$arr = [$a[$field], $b[$field]];

				$res = false;
				if (extension_loaded('intl') && class_exists('\Collator')) {
					$collator->asort($arr, \Collator::SORT_REGULAR);

					$lastItem = array_pop($arr);
					if (is_string($lastItem)) {
						$res = $collator->compare($lastItem, $a[$field]);
					}
				}

				if ($res === false) {
					return -1;
				}

				return ($res <= 0) ? $int : -$int;
			});
		} else {
			usort($array, function ($a, $b) use ($collator, $field, $int) {
				$arr = [$a[$field], $b[$field]];

				$res = false;
				if (extension_loaded('intl') && class_exists('\Collator')) {
					$collator->asort($arr, \Collator::SORT_REGULAR);

					$lastItem = array_pop($arr);
					if (is_string($lastItem)) {
						$res = $collator->compare($lastItem, $a[$field]);
					}
				}

				if ($res === false) {
					return -1;
				}

				return ($res <= 0) ? $int : -$int;
			});
		}

		return $isLaravelCollection ? self::toCollection($array) : ($isObject ? self::toObject($array) : $array);
	}

	/**
	 * Object to Array
	 *
	 * @param $object
	 * @param int|null $level
	 * @param int $currentLevel
	 * @return array
	 * @author: edwardayen
	 *
	 * Example usage
	 * -------------
	 * $object = new \stdClass();
	 * $object->name = 'John';
	 * $object->age = 30;
	 * $object->address = new \stdClass();
	 * $object->address->street = '123 Main St';
	 * $object->address->city = 'Anytown';
	 * $object->address->postal = new \stdClass();
	 * $object->address->postal->zip = '12345';
	 * $object->address->postal->country = 'USA';
	 * $object->hobbies = ['reading', 'travelling'];
	 *
	 * $converted = Arr::fromObject($object, 2); // Convert up to level 2
	 * print_r($converted);
	 *
	 * $convertedAllLevels = Arr::fromObject($object); // Convert all levels
	 * print_r($convertedAllLevels);
	 */
	public static function fromObject($object, ?int $level = null, int $currentLevel = 0)
	{
		// If the input is not an object, return it as it is
		if (!is_object($object)) {
			return $object;
		}

		// If a specific level is set, and we have reached that level, return the object
		if ($level !== null && $currentLevel >= $level) {
			return $object;
		}

		// Convert the object to an array
		$array = [];
		foreach ($object as $key => $value) {
			if (is_object($value)) {
				// Recursively convert nested objects to arrays
				$array[$key] = self::fromObject($value, $level, $currentLevel + 1);
			} else {
				$array[$key] = $value;
			}
		}

		return $array;
	}

	/**
	 * Array to Object
	 *
	 * @param $array
	 * @param int|null $level
	 * @param int $currentLevel
	 * @return array|\stdClass
	 * @author: edwardayen
	 *
	 * Example usage
	 * -------------
	 * $array = [
	 *      'name' => 'John',
	 *      'age' => 30,
	 *      'address' => ['street' => '123 Main St', 'city' => 'Anytown', 'postal' => ['zip' => '12345', 'country' => 'USA']],
	 *      'hobbies' => ['reading', 'traveling']
	 * ];
	 *
	 * $converted = Arr::toObject($array, 2); // Convert up to level 2
	 * print_r($converted);
	 *
	 * $convertedAllLevels = Arr::toObject($array); // Convert all levels
	 * print_r($convertedAllLevels);
	 */
	public static function toObject($array, ?int $level = null, int $currentLevel = 0)
	{
		// If the input is not an array, return it as it is
		if (!is_array($array)) {
			return $array;
		}

		// If a specific level is set, and we have reached that level, return the array
		if ($level !== null && $currentLevel >= $level) {
			return $array;
		}

		// Convert the array to an object
		$object = new stdClass();
		foreach ($array as $key => $value) {
			if (is_array($value)) {
				// Recursively convert nested arrays to objects
				$object->$key = self::toObject($value, $level, $currentLevel + 1);
			} else {
				$object->$key = $value;
			}
		}

		return $object;
	}

	/**
	 * Array to Laravel Collection
	 *
	 * @param $array
	 * @return \Illuminate\Support\Collection
	 */
	public static function toCollection($array)
	{
		// If the input is not an array, return it as it is
		if (!is_array($array)) {
			return $array;
		}

		// Convert the array to a Laravel Collection
		$newArray = [];
		foreach ($array as $key => $value) {
			if (is_array($value)) {
				$newArray[$key] = self::toCollection($value);
			} else {
				$newArray[$key] = $value;
			}
		}

		return collect($newArray);
	}

	/**
	 * Array to HTML attributes string
	 *
	 * @param array $attributes
	 * @return string
	 */
	public static function toAttributes(array $attributes): string
	{
		$result = [];

		foreach ($attributes as $key => $value) {
			if (is_bool($value)) {
				if ($value) {
					$result[] = $key; // Render as: disabled, checked, etc.
				}
				continue;
			}

			if (is_null($value)) {
				continue;
			}

			// Handle array values (including multi-dimensional)
			if (is_array($value)) {
				// For data-* attributes, convert array to JSON
				if (str_starts_with($key, 'data-')) {
					$result[] = $key . '="' . e(json_encode($value, JSON_HEX_APOS | JSON_HEX_QUOT)) . '"';
				} // For style attribute, convert to CSS string
				else if ($key === 'style') {
					$styles = [];
					foreach ($value as $styleKey => $styleValue) {
						$styles[] = $styleKey . ': ' . $styleValue;
					}
					$result[] = 'style="' . e(implode('; ', $styles)) . '"';
				} // For class attribute, implode with spaces
				else if ($key === 'class') {
					$result[] = 'class="' . e(implode(' ', $value)) . '"';
				} // For other arrays, convert to space-separated string
				else {
					$result[] = $key . '="' . e(implode(' ', $value)) . '"';
				}
			} // Handle regular values
			else {
				$result[] = $key . '="' . e($value) . '"';
			}
		}

		return implode(' ', $result);
	}

	/**
	 * Array to HTML style attribute string
	 *
	 * @param array $styles Associative array of CSS properties and values
	 * @param bool $includeAttribute Whether to include 'style="..."' wrapper (default: true)
	 * @return string
	 */
	public static function toStyleAttribute(array $styles, bool $includeAttribute = true): string
	{
		if (empty($styles)) {
			return '';
		}

		$result = [];

		foreach ($styles as $property => $value) {
			// Skip null values
			if (is_null($value)) {
				continue;
			}

			// Handle boolean values (skip false, include property name only for true)
			if (is_bool($value)) {
				if ($value) {
					$result[] = $property;
				}
				continue;
			}

			// Handle array values (e.g., for CSS transforms or multiple shadows)
			if (is_array($value)) {
				$result[] = $property . ': ' . implode(', ', $value);
			} // Handle regular values
			else {
				$result[] = $property . ': ' . $value;
			}
		}

		if (empty($result)) {
			return '';
		}

		$styleString = implode('; ', $result);

		return $includeAttribute ? 'style="' . e($styleString) . '"' : $styleString;
	}

	/**
	 * array_unique multi dimension
	 *
	 * @param $array
	 * @return array|\Illuminate\Support\Collection|\stdClass
	 */
	public static function unique($array): array|Collection|stdClass
	{
		if (!is_array($array) && !is_object($array)) {
			return [];
		}

		if (is_object($array)) {
			if ($array instanceof Collection) {
				$array = $array->toArray();
				$array = self::unique($array);
				$array = self::toCollection($array);
			} else {
				$array = self::fromObject($array);
				$array = self::unique($array);
				$array = self::toObject($array);
			}
		} else {
			$array = array_map('serialize', $array);
			$array = array_map('base64_encode', $array);
			$array = array_unique($array);
			$array = array_map('base64_decode', $array);
			$array = array_map('unserialize', $array);
		}

		return $array;
	}

	/**
	 * shuffle for associative arrays, preserves key => value pairs.
	 *
	 * Shuffle associative and non-associative arrays while preserving key, value pairs.
	 * Also returns the shuffled array instead of shuffling it in place.
	 *
	 * @param $array
	 * @return array
	 */
	public static function shuffleAssoc($array): array
	{
		if (!is_array($array)) return [];
		if (empty($array)) return $array;

		$keys = array_keys($array);
		shuffle($keys);

		$random = [];
		foreach ($keys as $key) {
			$random[$key] = $array[$key];
		}

		return $random;
	}

	/**
	 * This function will remove all the specified keys from an array and return the final array.
	 * Arguments: The first argument is the array that should be edited
	 *            The arguments after the first argument is a list of keys that must be removed.
	 * Example: array_remove_key($arr, "one", "two", "three");
	 * Return: The function will return an array after deleting the said keys
	 */
	public static function removeKey()
	{
		$args = func_get_args();
		$arr = $args[0];
		$keys = array_slice($args, 1);
		foreach ($arr as $k => $v) {
			if (in_array($k, $keys)) {
				unset($arr[$k]);
			}
		}

		return $arr;
	}

	/**
	 * This function will remove all the specified values from an array and return the final array.
	 * Arguments: The first argument is the array that should be edited
	 *            The arguments after the first argument is a list of values that must be removed.
	 * Example: array_remove_value($arr,"one","two","three");
	 * Return: The function will return an array after deleting the said values
	 */
	public static function removeValue()
	{
		$args = func_get_args();
		$arr = $args[0];
		$values = array_slice($args, 1);
		foreach ($arr as $k => $v) {
			if (in_array($v, $values)) {
				unset($arr[$k]);
			}
		}

		return $arr;
	}

	/**
	 * array_diff_assoc() recursive
	 *
	 * @param $array1
	 * @param $array2
	 * @param bool $checkValues
	 * @return array
	 */
	public static function diffAssoc($array1, $array2, bool $checkValues = false): array
	{
		$difference = [];
		foreach ($array1 as $key => $value) {
			if (is_array($value)) {
				if (!isset($array2[$key]) || !is_array($array2[$key])) {
					$difference[$key] = $value;
				} else {
					$newDiff = self::diffAssoc($value, $array2[$key]);
					if (!empty($newDiff))
						$difference[$key] = $newDiff;
				}
			} else if (!array_key_exists($key, $array2)) {
				$difference[$key] = $value;
			}

			// Check if the values is different
			if ($checkValues) {
				if (array_key_exists($key, $array2) && $array2[$key] !== $value) {
					$difference[$key] = $value;
				}
			}
		}

		return $difference;
	}

	/**
	 * Arr::undot() for Language Key
	 * Convert a flatten "dot" notation array into an expanded array.
	 *
	 * @param iterable $array
	 * @return array
	 */
	public static function arrUndot($array): array
	{
		$results = [];

		foreach ($array as $key => $value) {
			static::arrSet($results, $key, $value);
		}

		return $results;
	}

	/**
	 * Arr::set() for Language Key
	 * Set an array item to a given value using "dot" notation with a limit.
	 *
	 * If no key is given to the method, the entire array will be replaced.
	 *
	 * @param $array
	 * @param $key
	 * @param $value
	 * @param int $limit
	 * @return array|mixed
	 */
	public static function arrSet(&$array, $key, $value, int $limit = -1)
	{
		if (is_null($key)) {
			return $array = $value;
		}

		if (str_contains($key, '.')) {
			$key = preg_replace("/[.]{3}/ui", "{###}$1", $key);
			$key = preg_replace("/\.(\s+)/ui", "{***}$1", $key);
			$key = preg_replace("/\.$/ui", "{***}", $key);

			// dump($key); // Debug!
		}

		$keys = preg_split('/\./ui', $key, $limit);

		/*
		// Debug!
		if (!str_starts_with($key, '*.')) {
			if (str_contains($key, '.')) {
				dump($keys);
			}
		}
		*/

		foreach ($keys as $i => $key) {
			if (count($keys) === 1) {
				break;
			}

			unset($keys[$i]);

			$key = str_replace(['{###}', '{***}'], ['...', '.'], $key);

			// If the key doesn't exist at this depth, we will just create an empty array
			// to hold the next value, allowing us to create the arrays to hold final
			// values at the correct depth. Then we'll keep digging into the array.
			if (!isset($array[$key]) || !is_array($array[$key])) {
				$array[$key] = [];
			}

			$array = &$array[$key];
		}

		$key = array_shift($keys);
		$key = str_replace(['{###}', '{***}'], ['...', '.'], $key);

		$array[$key] = $value;

		return $array;
	}

	/**
	 * Flatten a POST request ($_POST) array to a key value pair
	 *
	 * @param $postData
	 * @param string $prefix
	 * @return array
	 */
	public static function flattenPost($postData, string $prefix = ''): array
	{
		$result = [];

		foreach ($postData as $key => $value) {
			$newKey = ($prefix == '') ? ($prefix . $key) : ($prefix . '[' . $key . ']');
			if (is_array($value)) {
				$result = $result + self::flattenPost($value, $newKey);
			} else {
				$newKey .= ''; // Force the value to be string
				$result[$newKey] = $value;
			}
		}

		return $result;
	}

	/**
	 * Check if the key exists in an array (or arrayable object)
	 *
	 * Note: Like the native PHP 'array_key_exists',
	 * this function can check if a key exists in:
	 * Array, stdClass object, Laravel collection, Laravel model object, or JSON
	 *
	 * @param string $key
	 * @param $object
	 * @return bool
	 */
	public static function keyExists(string $key, $object): bool
	{
		if (is_array($object)) {
			return array_key_exists($key, $object);
		}

		if ($object instanceof stdClass) {
			return array_key_exists($key, Arr::fromObject($object));
		}

		if ($object instanceof Collection || $object instanceof Model) {
			return array_key_exists($key, $object->toArray());
		}

		if (is_string($object)) {
			if (str($object)->isJson()) {
				return array_key_exists($key, json_decode($object, true));
			}
		}

		return false;
	}

	/**
	 * @param array $arrayToSort
	 * @param array $arrayReference
	 * @return array
	 */
	public static function sortByReference(array $arrayToSort, array $arrayReference): array
	{
		usort($arrayToSort, function ($a, $b) use ($arrayReference) {
			$posA = array_search($a, $arrayReference);
			$posB = array_search($b, $arrayReference);

			return $posA - $posB;
		});

		return $arrayToSort;
	}

	/**
	 * @param array $arrayToSort
	 * @param array $arrayReference
	 * @param string|null $key
	 * @return array
	 */
	public static function sortAssocByReference(array $arrayToSort, array $arrayReference, ?string $key = null): array
	{
		usort($arrayToSort, function ($a, $b) use ($arrayReference, $key) {
			// If it's a multidimensional array, get the value of the key
			$valueA = $key ? $a[$key] : $a;
			$valueB = $key ? $b[$key] : $b;

			$posA = array_search($valueA, $arrayReference);
			$posB = array_search($valueB, $arrayReference);

			return $posA - $posB;
		});

		return $arrayToSort;
	}

	/**
	 * Reorder array vertically in columns
	 *
	 * @param array $sortedArray
	 * @param int $columns
	 * @return array
	 */
	public static function reorderToColumns(array $sortedArray, int $columns): array
	{
		$rows = ceil(count($sortedArray) / $columns); // Calculate the number of rows
		$grid = array_chunk($sortedArray, $rows);     // Split the array into chunks per column

		// Adjust to display the array items per column
		$reordered = [];
		for ($i = 0; $i < $rows; $i++) {
			foreach ($grid as $column) {
				if (isset($column[$i])) {
					$reordered[] = $column[$i];
				}
			}
		}

		return $reordered;
	}
}
