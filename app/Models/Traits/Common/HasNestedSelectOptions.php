<?php

/*
 * LaraClassifier - Classified Ads Web Application
 * Copyright (c) BeDigit. All Rights Reserved
 *
 * Website: https://bedigit.com
 * Author: Mayeul Akpovi (BeDigit)
 *
 * LICENSE
 * -------
 * This software is provided under a license agreement and may only be used or copied
 * in accordance with its terms, including the inclusion of the above copyright notice.
 * As this software is sold exclusively on CodeCanyon,
 * please review the full license details here: https://codecanyon.net/licenses/standard
 */

namespace App\Models\Traits\Common;

use App\Models\Builders\Classes\GlobalBuilder;
use Closure;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Schema;
use Throwable;

/*
 * Trait for generating hierarchical select options from nested models
 *
 * This trait provides functionality to convert nested/tree structures into
 * flat arrays suitable for HTML select elements with proper indentation.
 *
 * Requirements:
 * - Model must have a 'children' relationship
 * - Model must have a 'roots' scope
 * - Model should be ordered by 'lft' for nested sets (or implement custom ordering)
 */

trait HasNestedSelectOptions
{
	/*
	|--------------------------------------------------------------------------
	| SCOPES
	|--------------------------------------------------------------------------
	*/
	#[Scope]
	protected function roots(GlobalBuilder $query): void
	{
		$query->columnIsEmpty('parent_id');
	}
	
	#[Scope]
	protected function childrenOf(Builder $query, $parentId): void
	{
		$query->where('parent_id', '=', $parentId);
	}
	
	/*
	|--------------------------------------------------------------------------
	| SELECT OPTIONS METHODS
	|--------------------------------------------------------------------------
	*/
	/**
	 * Generate hierarchical select options from nested model data
	 *
	 * @param int|null $excludeId ID to exclude from the tree (useful for preventing self-reference)
	 * @param Collection|null $entries Collection to process (null = fetch from DB)
	 * @param array $options Accumulated options array (passed by reference)
	 * @param int $currentDepth Current nesting depth (0 = root level)
	 * @param int $maxDepth Maximum nesting depth (0 = unlimited)
	 * @param string $indentString Characters used for visual indentation
	 * @param Closure|null $queryModifier Custom query callback for additional conditions
	 * @param bool $includeRootOption Whether to include a "Root" option at the top
	 * @param array $fieldMapping Column mapping ['key' => 'id_column', 'label' => 'name_column']
	 * @return array Flat array of options suitable for select elements [id => 'indented label']
	 */
	public static function getNestedSelectOptions(
		?int        $excludeId = null,
		?Collection $entries = null,
		array       &$options = [],
		int         $currentDepth = 0,
		int         $maxDepth = 0,
		string      $indentString = '-----', // ──, ••, -----
		?Closure    $queryModifier = null,
		bool        $includeRootOption = true,
		array       $fieldMapping = []
	): array
	{
		// Normalize field mapping with defaults
		$fields = self::normalizeFieldMapping($fieldMapping);
		
		// Handle initial call vs recursive call
		if ($entries === null) {
			// First call - initialize and fetch root entries
			$options = $includeRootOption ? [0 => self::getRootOptionLabel()] : [];
			$entries = self::buildNestedQuery($excludeId, $queryModifier, $fields)->get();
		} else {
			// Entries provided - initialize options if needed
			if (empty($options) && $includeRootOption) {
				$options = [0 => self::getRootOptionLabel()];
			}
		}
		
		// Early return if no entries to process
		if ($entries->isEmpty()) {
			return $options;
		}
		
		// Respect depth limit
		if ($maxDepth > 0 && $currentDepth >= $maxDepth) {
			return $options;
		}
		
		// Process each entry
		foreach ($entries as $entry) {
			// Skip excluded entry
			if ($entry->{$fields['key']} === $excludeId) {
				continue;
			}
			
			// Generate indented label
			$indent = $currentDepth > 0 ? str_repeat($indentString, $currentDepth) . '| ' : '';
			$options[$entry->{$fields['key']}] = $indent . $entry->{$fields['label']};
			
			// Process children recursively
			$entry = $entry->withRelationshipAutoloading();
			if ($entry->relationLoaded('children') && $entry->children->isNotEmpty()) {
				self::getNestedSelectOptions(
					$excludeId,
					$entry->children,
					$options,
					$currentDepth + 1,
					$maxDepth,
					$indentString,
					$queryModifier,
					false, // Never include root in recursive calls
					$fieldMapping
				);
			}
		}
		
		return $options;
	}
	
	/**
	 * Build the base query for fetching nested entries
	 *
	 * @param array $fields Normalized field mapping
	 */
	private static function buildNestedQuery(?int $excludeId, ?Closure $queryModifier, array $fields): Builder
	{
		$keyField = $fields['key'];
		
		$query = self::query()
			->with([
				'children' => function ($childQuery) use ($keyField, $excludeId) {
					// Recursively exclude from children
					if ($excludeId !== null) {
						$childQuery->where($keyField, '!=', $excludeId);
					}
				},
			])
			->roots();
		
		// Add default ordering if available
		if (self::hasColumn('lft')) {
			$query->orderBy('lft');
		} else if (self::hasColumn($fields['label'])) {
			$query->orderBy($fields['label']);
		}
		
		// Exclude from root level
		if ($excludeId !== null) {
			$query->where($keyField, '!=', $excludeId);
		}
		
		// Apply custom query modifications
		if ($queryModifier !== null) {
			$queryModifier($query);
		}
		
		return $query;
	}
	
	/**
	 * Normalize field mapping with sensible defaults
	 */
	private static function normalizeFieldMapping(array $fieldMapping): array
	{
		return [
			'key'   => $fieldMapping['key'] ?? 'id',
			'label' => $fieldMapping['label'] ?? self::guessLabelField(),
		];
	}
	
	/**
	 * Guess the most appropriate label field for the model
	 */
	private static function guessLabelField(): string
	{
		$possibleFields = ['name', 'title', 'label', 'text', 'description'];
		
		foreach ($possibleFields as $field) {
			if (self::hasColumn($field)) {
				return $field;
			}
		}
		
		return 'id'; // Fallback to ID if no suitable field found
	}
	
	/**
	 * Check if the model has a specific column
	 */
	private static function hasColumn(string $column): bool
	{
		try {
			return Schema::hasColumn((new static)->getTable(), $column);
		} catch (Throwable $e) {
			return false;
		}
	}
	
	/**
	 * Get the root option label (can be overridden by models)
	 */
	protected static function getRootOptionLabel(): string
	{
		return trans()->has('global.Root') ? trans('global.Root') : 'Root';
	}
	
	/**
	 * Convenient method with commonly used parameters
	 */
	public static function selectOptionsTree(
		?int     $excludeId = null,
		?Closure $queryModifier = null,
		int      $maxDepth = 0,
		bool     $includeRoot = true
	): array
	{
		return self::getNestedSelectOptions(
			excludeId: $excludeId,
			maxDepth: $maxDepth,
			queryModifier: $queryModifier,
			includeRootOption: $includeRoot
		);
	}
	
	/*
	|--------------------------------------------------------------------------
	| OTHER METHODS
	|--------------------------------------------------------------------------
	*/
	
	public static function getParentsIds($entryId, ?array &$parentsIds = []): ?array
	{
		$classBaseName = str(class_basename(self::class))->camel()->toString();
		$selectColumns = ['id', 'parent_id'];
		
		// Cache Parameters
		$cacheParams = [
			'action'        => "get.{$classBaseName}",
			'with'          => 'parent',
			'id'            => $entryId,
			'selectColumns' => implode(',', $selectColumns),
		];
		
		// Cached Query
		$entry = caching()->remember(self::class, $cacheParams, function () use ($entryId, $selectColumns) {
			return self::query()->with('parent')->where('id', $entryId)->first($selectColumns);
		});
		
		if (!empty($entry)) {
			$parentsIds[$entry->id] = $entry->id;
			if (!empty($entry->parent_id)) {
				if (!empty($entry->parent)) {
					return self::getParentsIds($entry->parent->id, $parentsIds);
				}
			}
		}
		
		return $parentsIds;
	}
}
