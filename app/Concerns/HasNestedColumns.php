<?php


namespace App\Concerns;

use App\Helpers\Common\HierarchicalData\Library\AdjacentToNested;
use App\Http\Controllers\Web\Admin\LanguageController;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

trait HasNestedColumns
{
	/**
	 * Adding new nested nodes
	 *
	 * @param $entry
	 * @return mixed
	 */
	protected function creatingNestedItem($entry): mixed
	{
		/** @var Model|MenuItem $modelClass */
		$modelClass = get_class($entry);

		// Find new left position & new depth
		$newLft = 0;
		$newDepth = 0;
		if (!empty($entry->parent_id)) {
			// Node (will) have a parent
			$parent = $modelClass::query()->find($entry->parent_id);

			if (!empty($parent)) {
				$newLft = $parent->lft; // <- Parent does not have children
				$newDepth = $parent->depth + 1;

				$lastChild = $modelClass::query()
					->childrenOf($parent->id)
					->where('id', '!=', $entry->id)
					->orderByDesc('rgt')
					->first();

				if (!empty($lastChild)) {
					$newLft = $lastChild->rgt; // <- Parent has children
				}
			}
		} else {
			// Node (will) not have a parent
			$latest = $modelClass::query()->orderByDesc('rgt')->first();

			if (!empty($latest)) {
				$newLft = $latest->rgt;
			}
		}

		$tableName = (new $modelClass)->getTable();

		// Create new space for subtree
		$affected = DB::table($tableName)->where('rgt', '>', $newLft)->update(['rgt' => DB::raw('rgt + 2')]);
		$affected = DB::table($tableName)->where('lft', '>', $newLft)->update(['lft' => DB::raw('lft + 2')]);

		// Update the lft, rgt & the depth columns for the new node
		$entry->lft = $newLft + 1;
		$entry->rgt = $newLft + 2;
		$entry->depth = $newDepth;

		return $entry;
	}

	/**
	 * Updating (Moving) nested nodes
	 *
	 * @param $entry
	 * @return mixed
	 */
	protected function updatingNestedItem($entry): mixed
	{
		/** @var Model $modelClass */
		$modelClass = get_class($entry);

		// Escape from mass update
		if ($this->isFromMassUpdate()) {
			return $entry;
		}

		// Get the original object values
		$original = $entry->getOriginal();

		// Check some columns
		if (
			empty($original)
			|| !array_key_exists('parent_id', $original)
			|| !array_key_exists('lft', $original)
			|| !array_key_exists('rgt', $original)
		) {
			return $entry;
		}

		// Since this method is not run during the reorder update,
		// don't update nodes if the 'parent_id' column is not changed
		if ($original['parent_id'] == $entry->parent_id) {
			return $entry;
		}

		// Find new left & right position & new depth
		$newLft = 0;
		$newDepth = 0;

		if (!empty($entry->parent_id)) {
			// Node (will) have a parent
			$parent = $modelClass::query()->find($entry->parent_id);

			if (!empty($parent)) {
				$newLft = $parent->lft; // <- Parent does not have children
				$newDepth = $parent->depth + 1;

				$lastChild = $modelClass::query()
					->childrenOf($parent->id)
					->where('id', '!=', $entry->id)
					->orderByDesc('rgt')
					->first();

				if (!empty($lastChild)) {
					$newLft = $lastChild->rgt; // <- Parent has children
				}
			}
		} else {
			// Node (will) not have a parent
			$latest = $modelClass::query()->orderByDesc('rgt')->first();

			if (!empty($latest)) {
				$newLft = $latest->rgt;
			}
		}

		// Calculate position adjustment variables
		// Get space between rgt & lft + 1
		$width = $original['rgt'] - $original['lft'] + 1;

		$tableName = (new $modelClass)->getTable();

		// Adding an existing node to a position (Moving a node)
		$affected = DB::table($tableName)->where('lft', '>', $newLft)->update(['lft' => DB::raw('lft + ' . $width)]);
		$affected = DB::table($tableName)->where('rgt', '>', $newLft)->update(['rgt' => DB::raw('rgt + ' . $width)]);

		// Update the new position & the depth column of the moved node
		$entry->lft = $newLft + 1;
		$entry->rgt = $newLft + $width;
		$entry->depth = $newDepth;

		return $entry;
	}

	/**
	 * Deleting nested nodes
	 *
	 * @param $entry
	 */
	protected function deletingNestedItem($entry): void
	{
		/** @var Model $modelClass */
		$modelClass = get_class($entry);

		$tableName = (new $modelClass)->getTable();

		// Get space between rgt & lft + 1
		$width = $entry->rgt - $entry->lft + 1;

		// Remove old space vacated by subtree (After deleting nodes)
		$affected = DB::table($tableName)->where('lft', '>', $entry->rgt)->update(['lft' => DB::raw('lft - ' . $width)]);
		$affected = DB::table($tableName)->where('rgt', '>', $entry->rgt)->update(['rgt' => DB::raw('rgt - ' . $width)]);
	}

	/**
	 * Delete the category's children recursively
	 *
	 * @param $entry
	 */
	protected function deleteChildrenRecursively($entry): void
	{
		/** @var Model $modelClass */
		$modelClass = get_class($entry);

		if (!empty($entry) && isset($entry->id)) {
			$subCats = $modelClass::query()->with('children')->childrenOf($entry->id)->get();
			if ($subCats->count() > 0) {
				foreach ($subCats as $subCat) {
					if (isset($subCat->children) && $subCat->children->count() > 0) {
						$this->deleteChildrenRecursively($subCat);
					}

					$subCat->delete();
				}
			}
		}
	}

	/**
	 * Convert Adjacent List to Nested Set (By giving the Item's Language)
	 * NOTE: Need to use adjacent list model to add, update or delete nodes
	 *
	 * @param $entry
	 * @return void
	 * @throws \App\Exceptions\Custom\CustomException
	 */
	protected function adjacentToNestedByItem($entry): void
	{
		/** @var Model $modelClass */
		$modelClass = get_class($entry);

		// Escape from mass update
		if ($this->isFromMassUpdate()) {
			return;
		}

		$tableName = (new $modelClass)->getTable();

		$params = [
			'adjacentTable' => $tableName,
			'nestedTable'   => $tableName,
		];

		$transformer = new AdjacentToNested($params);

		$transformer->getAndSetAdjacentItemsIds();
		$transformer->convertChildrenRecursively(0);
		$transformer->setNodesDepth();
	}

	/**
	 * Escape from mass update
	 *
	 * @return bool
	 */
	private function isFromMassUpdate(): bool
	{
		// Escape from mass update. ie:
		// - CategoryController (only for reorder() & saveReorder() methods)
		// - LanguageController (all methods)
		if (
			request()->is('*/reorder')
			|| routeActionHas('@reorder')
			|| routeActionHas('@saveReorder')
			|| routeActionHas(LanguageController::class)
		) {
			return true;
		}

		return false;
	}
}
