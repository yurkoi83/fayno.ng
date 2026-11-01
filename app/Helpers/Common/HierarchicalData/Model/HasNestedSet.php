<?php

namespace App\Helpers\Common\HierarchicalData\Model;

use App\Helpers\Common\HierarchicalData\Library\NestedSetManager;
use Illuminate\Database\Eloquent\Model;

/*
 * Usage Examples
 *
 * use HasNestedSet;
 *
 * Add a Node
 * $category = Category::addNode(['name' => 'New Category', 'slug' => 'new-category'], parentId: 1);
 *
 * Delete a Node
 * Category::deleteNode(nodeId: 2);
 *
 * Move a Node
 * Category::moveNode(nodeId: 2, newParentId: 3);
 */

trait HasNestedSet
{
	/**
	 * Add a new node to the nested set.
	 *
	 * @param array $data The data for the new node.
	 * @param int|null $parentId The ID of the parent node (optional).
	 * @return \Illuminate\Database\Eloquent\Model
	 */
	public static function insertNode(array $data, ?int $parentId = null): Model
	{
		return (new NestedSetManager)->insertNode(static::class, $data, $parentId);
	}
	
	/**
	 * Delete a node from the nested set.
	 *
	 * @param int $nodeId The ID of the node to delete.
	 * @return void
	 */
	public static function deleteNode(int $nodeId): void
	{
		(new NestedSetManager)->deleteNode(static::class, $nodeId);
	}
	
	/**
	 * Move a node within the nested set.
	 *
	 * @param int $nodeId The ID of the node to move.
	 * @param int|null $newParentId The ID of the new parent node (optional).
	 * @return void
	 */
	public static function moveNode(int $nodeId, ?int $newParentId = null): void
	{
		(new NestedSetManager)->moveNode(static::class, $nodeId, $newParentId);
	}
	
	/**
	 * Build a PARTIAL tree (nested array) for the subtree under a given node.
	 *
	 * @param int $nodeId
	 * @param bool $normalizeDepth
	 * @return array
	 */
	public static function buildSubtree(int $nodeId, bool $normalizeDepth = false): array
	{
		return (new NestedSetManager)->buildSubtree(static::class, $nodeId, $normalizeDepth);
	}
	
	/**
	 * Build a tree (nested array) of ALL nodes, sorted by `lft`.
	 *
	 * @return array
	 */
	public static function buildTree(): array
	{
		return (new NestedSetManager)->buildTree(static::class);
	}
}
