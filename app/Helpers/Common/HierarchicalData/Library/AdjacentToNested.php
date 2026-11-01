<?php

namespace App\Helpers\Common\HierarchicalData\Library;

use App\Enums\CategoryType;
use App\Exceptions\Custom\CustomException;
use App\Helpers\Common\Arr;
use App\Helpers\Common\DBUtils;
use App\Helpers\Common\DBUtils\DBIndex;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Convert Adjacent List model to Nested Set model
 *
 * DEFINITIONS:
 * - Adjacency List model uses parent_id column
 * - Nested Sets model uses left/right (lft/rgt) boundaries
 *
 * NOTE: The Adjacent List model root entries' parent_id column need to be set as 'null' (instead of 0).
 */

class AdjacentToNested
{
	public string $adjacentTable = 'adjacent';
	public string $nestedTable = 'nested';
	public string $primaryKeyColumn = 'id';
	public string $labelColumn = 'name';
	
	public string $parentColumn = 'parent_id';
	public string $leftColumn = 'lft';
	public string $rightColumn = 'rgt';
	public string $depthColumn = 'depth';
	
	public bool $ordered = false;
	
	private int $iCount;
	private array $adjacentItemsIdsArray;
	
	/**
	 * @param array $params
	 */
	public function __construct(array $params = [])
	{
		$this->adjacentTable = $params['adjacentTable'] ?? $this->adjacentTable;
		$this->nestedTable = $params['nestedTable'] ?? $this->nestedTable;
		$this->primaryKeyColumn = $params['primaryKeyColumn'] ?? $this->primaryKeyColumn;
		$this->labelColumn = $params['labelColumn'] ?? $this->getLabelColumn();
		
		$this->parentColumn = $params['parentColumn'] ?? $this->parentColumn;
		$this->leftColumn = $params['leftColumn'] ?? $this->leftColumn;
		$this->rightColumn = $params['rightColumn'] ?? $this->rightColumn;
		$this->depthColumn = $params['depthColumn'] ?? $this->depthColumn;
	}
	
	/**
	 * Get & Set the adjacent table items IDs
	 *
	 * @return array
	 * @throws \App\Exceptions\Custom\CustomException
	 */
	public function getAndSetAdjacentItemsIds(): array
	{
		$this->checkTablesAndColumns();
		
		// Get all the adjacent items
		$adjacentItems = DB::table($this->adjacentTable);
		if ($this->ordered) {
			$adjacentItems = $adjacentItems->orderBy($this->leftColumn);
		}
		
		$tab = [];
		if ($adjacentItems->count() > 0) {
			$adjacentItems = $adjacentItems->get();
			foreach ($adjacentItems as $item) {
				if (!Schema::hasColumn($this->adjacentTable, $this->parentColumn)) {
					continue;
				}
				
				$parentId = $item->{$this->parentColumn};
				$childId = $item->{$this->primaryKeyColumn};
				
				if ($parentId == 0) {
					$parentId = null;
				}
				
				if (!array_key_exists($parentId, $tab)) {
					$tab[$parentId] = [];
				}
				
				$tab[$parentId][] = $childId;
			}
		}
		
		$this->setAdjacentItemsIds($tab);
		
		return $tab;
	}
	
	/**
	 * @param $adjacentItemsIdsArray
	 * @return void
	 * @throws \App\Exceptions\Custom\CustomException
	 */
	public function setAdjacentItemsIds($adjacentItemsIdsArray): void
	{
		if (!is_array($adjacentItemsIdsArray)) {
			$msg = "First parameter should be an array. Instead, it was type '" . gettype($adjacentItemsIdsArray) . "'";
			throw new CustomException($msg);
		}
		
		$this->iCount = 1;
		if (!empty($adjacentItemsIdsArray)) {
			$this->adjacentItemsIdsArray = $adjacentItemsIdsArray;
		}
	}
	
	/**
	 * Convert the adjacent items to nested set model into the nested table
	 *
	 * @param $parentId
	 * @throws \App\Exceptions\Custom\CustomException
	 */
	public function convertChildrenRecursively($parentId): void
	{
		if ($parentId == 0) {
			$parentId = null;
		}
		
		$iLeft = $this->iCount;
		$this->iCount++;
		
		$children = $this->getChildren($parentId);
		if (!empty($children)) {
			foreach ($children as $childId) {
				$this->convertChildrenRecursively($childId);
			}
		}
		
		$iRight = $this->iCount;
		$this->iCount++;
		
		// Convert!
		$this->updateItem($iLeft, $iRight, $parentId);
	}
	
	/**
	 * Find and Set the nodes depth
	 *
	 * @return void
	 * @throws \App\Exceptions\Custom\CustomException
	 */
	public function setNodesDepth(): void
	{
		$this->checkTablesAndColumns();
		
		$nestedTable = DBUtils::table($this->nestedTable);
		
		// Finding the Depth of the nodes
		if (Schema::hasColumn($this->adjacentTable, $this->labelColumn)) {
			$sql = "SELECT node.{$this->primaryKeyColumn},
       				node.{$this->labelColumn},
       				(COUNT(parent.{$this->labelColumn}) - 1) AS {$this->depthColumn}
				FROM {$nestedTable} AS node, {$nestedTable} AS parent
				WHERE node.{$this->leftColumn}
				    BETWEEN parent.{$this->leftColumn} AND parent.{$this->rightColumn}
				GROUP BY node.{$this->primaryKeyColumn}, node.{$this->labelColumn}
				ORDER BY node.{$this->primaryKeyColumn};";
		} else {
			$sql = "SELECT node.{$this->primaryKeyColumn},
       				(COUNT(parent.{$this->primaryKeyColumn}) - 1) AS {$this->depthColumn}
				FROM {$nestedTable} AS node, {$nestedTable} AS parent
				WHERE node.{$this->leftColumn}
				    BETWEEN parent.{$this->leftColumn} AND parent.{$this->rightColumn}
				GROUP BY node.{$this->primaryKeyColumn}
				ORDER BY node.{$this->primaryKeyColumn};";
		}
		$items = DB::select($sql);
		
		if (is_array($items) && count($items) > 0) {
			foreach ($items as $item) {
				$itemArray = Arr::fromObject($item);
				
				if (!isset($itemArray[$this->primaryKeyColumn])) {
					continue;
				}
				
				$newArray = [
					$this->depthColumn => $itemArray[$this->depthColumn],
				];
				
				// Set the item's depth
				$affected = DB::table($this->nestedTable)
					->where($this->primaryKeyColumn, $itemArray[$this->primaryKeyColumn])
					->update($newArray);
			}
		}
	}
	
	private function getLabelColumn(): string
	{
		$possibleLabelColumns = ['name', 'title', 'label'];
		foreach ($possibleLabelColumns as $labelColumn) {
			if (Schema::hasColumn($this->nestedTable, $labelColumn)) {
				return $labelColumn;
			}
		}
		
		return 'name';
	}
	
	/**
	 * Create the Nested Set indexes
	 *
	 * @return void
	 * @throws \App\Exceptions\Custom\CustomException
	 */
	public function createNestedSetIndexes(): void
	{
		$this->checkTablesAndColumns();
		
		// Make the 'lft' & 'rgt' columns unique and index the 'depth' column
		
		// Check if a unique indexes key exist, and drop it.
		DBIndex::dropIndexIfExists($this->nestedTable, $this->leftColumn);
		DBIndex::dropIndexIfExists($this->nestedTable, $this->rightColumn);
		DBIndex::dropIndexIfExists($this->nestedTable, $this->depthColumn);
		
		// Create indexes
		DBIndex::createIndexIfNotExists($this->nestedTable, $this->leftColumn);  // Should be unique
		DBIndex::createIndexIfNotExists($this->nestedTable, $this->rightColumn); // Should be unique
		DBIndex::createIndexIfNotExists($this->nestedTable, $this->depthColumn);
	}
	
	/**
	 * @param $currentId
	 * @return mixed
	 */
	private function getChildren($currentId): mixed
	{
		if (!isset($this->adjacentItemsIdsArray[$currentId])) {
			return [];
		}
		
		return $this->adjacentItemsIdsArray[$currentId];
	}
	
	/**
	 * @param $iLeft
	 * @param $iRight
	 * @param $currentId
	 * @return void
	 * @throws \App\Exceptions\Custom\CustomException
	 */
	private function updateItem($iLeft, $iRight, $currentId): void
	{
		$this->checkTablesAndColumns();
		
		// Get the adjacent Item
		$adjacentItem = DB::table($this->adjacentTable)->find($currentId);
		if (empty($adjacentItem)) {
			return;
		}
		
		$adjacentItem = Arr::fromObject($adjacentItem);
		
		// Check the nested table structure & data
		if ($this->adjacentTable == $this->nestedTable) {
			if (
				!array_key_exists($this->leftColumn, $adjacentItem)
				|| !array_key_exists($this->rightColumn, $adjacentItem)
			) {
				return;
			}
			
			$nestedItem = $adjacentItem;
		} else {
			// Get the nested Item (If exists)
			$nestedItem = DB::table($this->nestedTable)->find($currentId);
		}
		
		// Update or Insert
		if (!empty($nestedItem)) {
			// Update the adjacentItem's 'lft' & 'rgt' values
			$newArray = [
				$this->leftColumn  => $iLeft,
				$this->rightColumn => $iRight,
			];
			
			// Required column — the only nested-set table that carries a
			// `type` column is `categories`, so the fallback comes from the
			// `CategoryType` enum (`CLASSIFIED`) to keep this single source
			// of truth across the codebase.
			if (array_key_exists('type', $adjacentItem)) {
				if (empty($adjacentItem['type'])) {
					$newArray['type'] = CategoryType::Classified->value;
				}
			}
			
			// Update the Item
			$affected = DB::table($this->nestedTable)
				->where('id', $currentId)
				->update($newArray);
		} else {
			// Update the adjacentItem's 'lft' & 'rgt' values
			$adjacentItem[$this->leftColumn] = $iLeft;
			$adjacentItem[$this->rightColumn] = $iRight;
			if (array_key_exists('type', $adjacentItem)) {
				if (empty($adjacentItem['type'])) {
					$adjacentItem['type'] = CategoryType::Classified->value;
				}
			}
			
			// Remove the primary key from the adjacentItem's array
			if (isset($adjacentItem[$this->primaryKeyColumn])) {
				unset($adjacentItem[$this->primaryKeyColumn]);
			}
			
			// Insert the Item
			DB::table($this->nestedTable)->insert($adjacentItem);
		}
	}
	
	/**
	 * Check the Tables and the Columns
	 *
	 * @return void
	 * @throws \App\Exceptions\Custom\CustomException
	 */
	private function checkTablesAndColumns(): void
	{
		$errTable = 'The table "%s" does not exist in the database.';
		$errColumn = 'The column "%s" does not exist in the table "%s".';
		
		// Check the adjacent table
		if (!Schema::hasTable($this->adjacentTable)) {
			throw new CustomException(sprintf($errTable, $this->adjacentTable));
		}
		if (!Schema::hasColumn($this->adjacentTable, $this->primaryKeyColumn)) {
			throw new CustomException(sprintf($errColumn, $this->primaryKeyColumn, $this->adjacentTable));
		}
		if (!Schema::hasColumn($this->adjacentTable, $this->parentColumn)) {
			throw new CustomException(sprintf($errColumn, $this->parentColumn, $this->adjacentTable));
		}
		
		// Check the nested table
		if (!Schema::hasTable($this->nestedTable)) {
			throw new CustomException(sprintf($errTable, $this->nestedTable));
		}
		if (!Schema::hasColumn($this->nestedTable, $this->primaryKeyColumn)) {
			throw new CustomException(sprintf($errColumn, $this->primaryKeyColumn, $this->nestedTable));
		}
		if (!Schema::hasColumn($this->nestedTable, $this->leftColumn)) {
			throw new CustomException(sprintf($errColumn, $this->leftColumn, $this->nestedTable));
		}
		if (!Schema::hasColumn($this->nestedTable, $this->rightColumn)) {
			throw new CustomException(sprintf($errColumn, $this->rightColumn, $this->nestedTable));
		}
	}
}
