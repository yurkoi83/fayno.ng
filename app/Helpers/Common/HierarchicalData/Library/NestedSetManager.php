<?php

namespace App\Helpers\Common\HierarchicalData\Library;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class NestedSetManager
{
	protected string $parentColumn = 'parent_id';
	protected string $leftColumn = 'lft';
	protected string $rightColumn = 'rgt';
	protected string $depthColumn = 'depth';
	
	/**
	 * Insert a new node as a child of the given parent ID (or as root).
	 *
	 * Wrap the entire process in a transaction to ensure consistency
	 * if something fails halfway.
	 */
	public function insertNode(string $modelClass, array $attributes, ?int $parentId = null): Model
	{
		/** @var Model $modelClass */
		return DB::transaction(function () use ($modelClass, $attributes, $parentId) {
			if (is_null($parentId)) {
				return $this->insertRoot($modelClass, $attributes);
			}
			
			$parent = $modelClass::findOrFail($parentId);
			$parentLeft = $parent->{$this->leftColumn};
			$parentRight = $parent->{$this->rightColumn};
			$parentDepth = $parent->{$this->depthColumn};
			
			// Shift boundaries
			$modelClass::where($this->rightColumn, '>=', $parentRight)->increment($this->rightColumn, 2);
			$modelClass::where($this->leftColumn, '>', $parentRight)->increment($this->leftColumn, 2);
			
			// Create the new node
			$newNode = new $modelClass($attributes);
			$newNode->{$this->parentColumn} = $parentId;
			$newNode->{$this->leftColumn} = $parentRight;
			$newNode->{$this->rightColumn} = $parentRight + 1;
			$newNode->{$this->depthColumn} = $parentDepth + 1;
			$newNode->save();
			
			return $newNode;
		});
	}
	
	/**
	 * Insert a new node as a root (no parent).
	 */
	protected function insertRoot(string $modelClass, array $attributes): Model
	{
		/** @var Model $modelClass */
		// This could also be wrapped in a transaction, but it's already inside
		// insertNode's transaction if that's how it's being called.
		$maxRight = $modelClass::max($this->rightColumn) ?? 0;
		
		$newNode = new $modelClass($attributes);
		$newNode->{$this->parentColumn} = null;
		$newNode->{$this->leftColumn} = $maxRight + 1;
		$newNode->{$this->rightColumn} = $maxRight + 2;
		$newNode->{$this->depthColumn} = 0;
		$newNode->save();
		
		return $newNode;
	}
	
	/**
	 * Delete a node (and all of its descendants).
	 *
	 * Use a transaction to ensure consistency.
	 */
	public function deleteNode(string $modelClass, int $nodeId): void
	{
		/** @var Model $modelClass */
		DB::transaction(function () use ($modelClass, $nodeId) {
			$node = $modelClass::findOrFail($nodeId);
			$left = $node->{$this->leftColumn};
			$right = $node->{$this->rightColumn};
			$width = $right - $left + 1;
			
			// 1) Delete all descendants (and the node itself)
			$modelClass::where($this->leftColumn, '>=', $left)->where($this->rightColumn, '<=', $right)->delete();
			
			// 2) Shift everything on the right back
			$modelClass::where($this->rightColumn, '>', $right)->decrement($this->rightColumn, $width);
			$modelClass::where($this->leftColumn, '>', $right)->decrement($this->leftColumn, $width);
		});
	}
	
	/**
	 * Move an existing node (and its entire subtree) under a new parent.
	 *
	 * Use a transaction to keep partial updates from corrupting the tree.
	 */
	public function moveNode(string $modelClass, int $nodeId, int $newParentId): void
	{
		/** @var Model $modelClass */
		DB::transaction(function () use ($modelClass, $nodeId, $newParentId) {
			$node = $modelClass::findOrFail($nodeId);
			$newParent = $modelClass::findOrFail($newParentId);
			
			// Basic check: ensure we don't move a node into its own subtree
			if (
				$newParent->{$this->leftColumn} >= $node->{$this->leftColumn}
				&& $newParent->{$this->rightColumn} <= $node->{$this->rightColumn}
			) {
				throw new RuntimeException('Cannot move a node inside its own subtree.');
			}
			
			$nodeLeft = $node->{$this->leftColumn};
			$nodeRight = $node->{$this->rightColumn};
			$width = $nodeRight - $nodeLeft + 1;
			
			// 1) Temporarily remove the subtree (map it to [1..$width])
			$modelClass::where($this->leftColumn, '>=', $nodeLeft)
				->where($this->rightColumn, '<=', $nodeRight)
				->update([
					$this->leftColumn  => DB::raw($this->leftColumn . ' - ' . ($nodeLeft - 1)),
					$this->rightColumn => DB::raw($this->rightColumn . ' - ' . ($nodeLeft - 1)),
				]);
			
			// 2) Close the gap in the original tree
			$modelClass::where($this->rightColumn, '>', $nodeRight)->decrement($this->rightColumn, $width);
			$modelClass::where($this->leftColumn, '>', $nodeRight)->decrement($this->leftColumn, $width);
			
			// 3) Make room at the new parent's position
			$newParent->refresh();
			$parentRight = $newParent->{$this->rightColumn};
			
			$modelClass::where($this->rightColumn, '>=', $parentRight)->increment($this->rightColumn, $width);
			$modelClass::where($this->leftColumn, '>', $parentRight)->increment($this->leftColumn, $width);
			
			// 4) Shift the subtree from [1..$width] to the new location
			$offset = $parentRight - 1;
			
			$modelClass::where($this->leftColumn, '<=', $width)
				->where($this->rightColumn, '<=', $width)
				->update([
					$this->leftColumn  => DB::raw($this->leftColumn . ' + ' . $offset),
					$this->rightColumn => DB::raw($this->rightColumn . ' + ' . $offset),
				]);
			
			// 5) Update the root node of the moved subtree
			$node->refresh();
			$node->{$this->parentColumn} = $newParentId;
			$node->{$this->depthColumn} = $newParent->{$this->depthColumn} + 1;
			$node->save();
			
			// 6) Update depth on all children in that subtree
			$this->updateSubtreeDepths($modelClass, $node);
		});
	}
	
	/**
	 * Recalculate `depth` for the entire subtree under the given node.
	 * (No transaction needed here because it's already called within the move transaction.)
	 */
	protected function updateSubtreeDepths(string $modelClass, Model $rootNode): void
	{
		/** @var Model $modelClass */
		$queue = [$rootNode];
		while ($queue) {
			/** @var Model $current */
			$current = array_shift($queue);
			
			// Get immediate children
			$children = $modelClass::where($this->parentColumn, $current->getKey())->get();
			foreach ($children as $child) {
				$child->{$this->depthColumn} = $current->{$this->depthColumn} + 1;
				$child->save();
				$queue[] = $child;
			}
		}
	}
	
	/**
	 * Build a PARTIAL tree (nested array) for the subtree under a given node.
	 */
	public function buildSubtree(string $modelClass, int $nodeId, bool $normalizeDepth = false): array
	{
		/** @var Model $modelClass */
		$node = $modelClass::findOrFail($nodeId);
		
		$subtreeNodes = $modelClass::query()
			->where($this->leftColumn, '>=', $node->{$this->leftColumn})
			->where($this->rightColumn, '<=', $node->{$this->rightColumn})
			->orderBy($this->leftColumn)
			->get();
		
		$depthOffset = $normalizeDepth ? $node->{$this->depthColumn} : 0;
		
		return $this->buildNestedArray($subtreeNodes, $depthOffset);
	}
	
	/**
	 * Build a tree (nested array) of ALL nodes, sorted by `lft`.
	 */
	public function buildTree(string $modelClass): array
	{
		/** @var Model $modelClass */
		$allNodes = $modelClass::orderBy($this->leftColumn)->get();

		return $this->buildNestedArray($allNodes);
	}

	/**
	 * Reorder the direct children of a single parent without rebuilding the
	 * rest of the tree. Each child keeps its subtree intact — only the
	 * sibling positions under `$parentId` change.
	 *
	 * Used by the admin per-parent drill-down reorder page (the page only
	 * ever shows one level at a time, so save costs scale with the number
	 * of children, not with the size of the tree). Each moved subtree is
	 * relocated with TWO bulk UPDATEs:
	 *   1) shift the subtree into a "scratch" range ABOVE the current
	 *      MAX(rgt) — guarantees no collision with the OLD positions of
	 *      unmoved siblings or NEW positions of others, and stays
	 *      non-negative (the lft/rgt columns are UNSIGNED in MySQL);
	 *   2) shift from scratch into the final target range.
	 *
	 * Children that exist in the DB but aren't in `$orderedChildIds` are
	 * appended at the end in their current relative order (defensive: a
	 * stale client payload shouldn't orphan them).
	 *
	 * @param string $modelClass
	 * @param int|null $parentId  null = reorder roots
	 * @param array<int, int> $orderedChildIds  child IDs in the new sibling order
	 */
	public function reorderChildren(string $modelClass, ?int $parentId, array $orderedChildIds): void
	{
		/** @var Model $modelClass */
		DB::transaction(function () use ($modelClass, $parentId, $orderedChildIds) {
			$instance = new $modelClass;
			$key = $instance->getKeyName();

			// 1) Load existing children + their subtree extent.
			$childrenQuery = $modelClass::query()
				->when(
					$parentId === null,
					fn ($q) => $q->whereNull($this->parentColumn),
					fn ($q) => $q->where($this->parentColumn, $parentId)
				)
				->orderBy($this->leftColumn);

			$children = $childrenQuery->get([$key, $this->leftColumn, $this->rightColumn]);

			if ($children->isEmpty()) {
				return;
			}

			// 2) Determine the cursor (where to start laying out children).
			//    Preserve whatever the parent's lft+1 currently is, or — for
			//    root reorders — the smallest existing root lft, so we don't
			//    drift the overall tree boundaries.
			if ($parentId === null) {
				$startCursor = (int) $children->min($this->leftColumn);
			} else {
				$parent = $modelClass::query()
					->where($key, $parentId)
					->first([$this->leftColumn]);
				if ($parent === null) {
					return;
				}
				$startCursor = (int) $parent->{$this->leftColumn} + 1;
			}

			// 3) Build the new order: requested ids in order, then any
			//    leftover children at the end (defensive against stale input).
			$childrenById = $children->keyBy($key);
			$requested = array_values(array_unique(array_map('intval', $orderedChildIds)));
			$ordered = [];
			foreach ($requested as $id) {
				if ($childrenById->has($id)) {
					$ordered[] = $childrenById[$id];
				}
			}
			foreach ($children as $child) {
				if (!in_array((int) $child->{$key}, $requested, true)) {
					$ordered[] = $child;
				}
			}

			// 4) Compute the per-subtree shift (delta) needed to land each
			//    child at its new lft. Skip children whose delta is zero.
			$shifts = [];
			$cursor = $startCursor;
			foreach ($ordered as $child) {
				$oldLft = (int) $child->{$this->leftColumn};
				$oldRgt = (int) $child->{$this->rightColumn};
				$width = $oldRgt - $oldLft + 1;
				$delta = $cursor - $oldLft;
				if ($delta !== 0) {
					$shifts[] = ['oldLft' => $oldLft, 'oldRgt' => $oldRgt, 'delta' => $delta];
				}
				$cursor += $width;
			}

			if ($shifts === []) {
				return;
			}

			// 5) Allocate a unique scratch slice ABOVE the current max(rgt)
			//    for each moved subtree. Staying positive keeps the UNSIGNED
			//    lft/rgt columns happy; the contiguous layout guarantees the
			//    scratch ranges don't collide with each other.
			$maxRgt = (int) ($modelClass::query()->max($this->rightColumn) ?? 0);
			$scratchCursor = $maxRgt + 1;
			foreach ($shifts as $i => $s) {
				$width = $s['oldRgt'] - $s['oldLft'] + 1;
				$shifts[$i]['scratchLft'] = $scratchCursor;
				$shifts[$i]['scratchRgt'] = $scratchCursor + $width - 1;
				// Always positive: scratch starts above max(rgt) >= oldLft.
				$shifts[$i]['toScratch'] = $scratchCursor - $s['oldLft'];
				$scratchCursor += $width;
			}

			// Pass 1: subtree → scratch (large positive shift).
			foreach ($shifts as $s) {
				$this->shiftLftRgt($modelClass, $s['oldLft'], $s['oldRgt'], $s['toScratch']);
			}

			// Pass 2: scratch → final position. The shift from scratchLft to
			// (oldLft + delta) is (newLft - scratchLft) — always negative
			// (scratchLft > maxRgt >= any newLft) but mathematically lands
			// the row on a positive value, so the UNSIGNED column never
			// underflows at the row level.
			foreach ($shifts as $s) {
				$newLft = $s['oldLft'] + $s['delta'];
				$shift = $newLft - $s['scratchLft'];
				$this->shiftLftRgt($modelClass, $s['scratchLft'], $s['scratchRgt'], $shift);
			}
		});
	}

	/**
	 * Bulk-update every row whose `lft` is in [$fromLft, $toLft] by adding
	 * `$shift` to both `lft` and `rgt`. Renders the sign explicitly so a
	 * negative shift emits `lft - N` rather than `lft + -N` (some MySQL
	 * versions reject the latter form in UNSIGNED contexts).
	 */
	private function shiftLftRgt(string $modelClass, int $fromLft, int $toLft, int $shift): void
	{
		if ($shift === 0) {
			return;
		}
		$op = $shift > 0 ? '+' : '-';
		$abs = abs($shift);
		/** @var Model $modelClass */
		$modelClass::query()
			->whereBetween($this->leftColumn, [$fromLft, $toLft])
			->update([
				$this->leftColumn  => DB::raw($this->leftColumn . ' ' . $op . ' ' . $abs),
				$this->rightColumn => DB::raw($this->rightColumn . ' ' . $op . ' ' . $abs),
			]);
	}

	/**
	 * Rebuild the entire nested-set tree (lft / rgt / depth / parent_id) for
	 * the given model from a flat, externally-ordered list of
	 * `{ id, parent_id, position }` rows. Used by the admin drag-and-drop
	 * reorder page, where the client sends back a flattened DOM ordering and
	 * we need to derive a fresh, consistent set of lft/rgt boundaries from it.
	 *
	 * `moveNode()` doesn't fit that workflow: it always appends to the end of
	 * the new parent's children, so it can't preserve the sibling order the
	 * user just produced by dragging. Rebuilding from scratch is the natural
	 * sibling primitive.
	 *
	 * Optional `$scope` restricts the rebuild to a subset of rows (e.g. all
	 * menu items belonging to a single menu). When provided, rows OUTSIDE the
	 * scope are left untouched — their lft / rgt slots are assumed to be in a
	 * separate numbering space (which is the case for menu items per menu_id).
	 * The rebuild numbers the scoped rows starting at 1; callers that share a
	 * single numbering space across scopes must not pass a scope.
	 *
	 * Items missing from the input are dropped at the end as roots (defensive:
	 * an out-of-sync DOM shouldn't orphan data). Items pointing at a parent_id
	 * that isn't in the input become roots for the same reason.
	 *
	 * @param string $modelClass
	 * @param array<int, array{id: int, parent_id: ?int, position: int}> $items
	 *   Each row carries the desired parent and the desired position WITHIN
	 *   that parent's children. Order across the full array does not matter —
	 *   we re-sort by (parent_id, position) before walking.
	 * @param (callable(\Illuminate\Database\Eloquent\Builder): void)|null $scope
	 *   Optional builder modifier applied to the "load known rows" query and
	 *   re-applied per UPDATE so we never touch rows outside the scope.
	 */
	public function rebuildFromOrderedList(string $modelClass, array $items, ?callable $scope = null): void
	{
		/** @var Model $modelClass */
		DB::transaction(function () use ($modelClass, $items, $scope) {
			// Normalize the input: drop rows without an id, coerce types.
			$normalized = [];
			foreach ($items as $item) {
				$id = (int) ($item['id'] ?? 0);
				if ($id <= 0) {
					continue;
				}
				$normalized[$id] = [
					'id'        => $id,
					'parent_id' => isset($item['parent_id']) && $item['parent_id'] !== '' && $item['parent_id'] !== null
						? (int) $item['parent_id']
						: null,
					'position'  => (int) ($item['position'] ?? 0),
				];
			}

			// Anything in the table but not in the input goes to the end as a
			// root, preserving its current relative order. This is defensive —
			// the happy path is "client sends every row".
			$query = $modelClass::query();
			if ($scope) {
				$scope($query);
			}
			$existingIds = $query->orderBy($this->leftColumn)->pluck('id')->all();
			$tailPosition = count($normalized);
			foreach ($existingIds as $id) {
				if (!isset($normalized[(int) $id])) {
					$normalized[(int) $id] = [
						'id'        => (int) $id,
						'parent_id' => null,
						'position'  => $tailPosition++,
					];
				}
			}

			// Re-parent orphans: if parent_id points at an id not in the set,
			// treat the node as a root rather than dropping it.
			foreach ($normalized as $id => $row) {
				if ($row['parent_id'] !== null && !isset($normalized[$row['parent_id']])) {
					$normalized[$id]['parent_id'] = null;
				}
			}

			// Group children by parent_id, sorted by their requested position.
			$childrenByParent = [];
			foreach ($normalized as $row) {
				$childrenByParent[$row['parent_id'] ?? 0][] = $row;
			}
			foreach ($childrenByParent as $parentId => $rows) {
				usort($childrenByParent[$parentId], static fn ($a, $b) => $a['position'] <=> $b['position']);
			}

			// Depth-first walk from the roots, allocating consecutive lft/rgt
			// pairs (modified preorder traversal). Counter starts at 1 to
			// match how `insertNode()` seeds new trees.
			$counter = 1;
			$updates = [];
			$walk = function (?int $parentId, int $depth) use (&$walk, &$counter, &$updates, &$childrenByParent): void {
				$rows = $childrenByParent[$parentId ?? 0] ?? [];
				foreach ($rows as $row) {
					$lft = $counter++;
					$walk($row['id'], $depth + 1);
					$rgt = $counter++;
					$updates[$row['id']] = [
						$this->parentColumn => $parentId,
						$this->leftColumn   => $lft,
						$this->rightColumn  => $rgt,
						$this->depthColumn  => $depth,
					];
				}
			};
			$walk(null, 0);

			// One UPDATE per row keeps the implementation simple and lets
			// model observers (cache invalidation, etc.) fire if the caller
			// wants them — though the trait that uses this method does its
			// own cache bust, so we stay with query-builder writes here to
			// avoid loading 8 attributes per row just to fire events.
			foreach ($updates as $id => $values) {
				$writeQuery = $modelClass::query();
				if ($scope) {
					$scope($writeQuery);
				}
				$writeQuery->where('id', $id)->update($values);
			}
		});
	}
	
	/**
	 * Converts a collection of nodes (already ordered by lft) into a nested array.
	 */
	protected function buildNestedArray($nodes, int $depthOffset = 0): array
	{
		$tree = [];
		$stack = [];
		
		foreach ($nodes as $node) {
			// Adjust depth if we're offsetting
			$depth = $node->{$this->depthColumn} - $depthOffset;
			
			$item = [
				'id'       => $node->getKey(),
				'name'     => $node->name ?? '',
				'depth'    => $depth,
				'children' => [],
			];
			
			if (empty($stack)) {
				$tree[] = $item;
				$stack[] = &$tree[array_key_last($tree)];
			} else {
				// While the top of the stack has a depth >= current node’s depth, pop it
				while (!empty($stack) && end($stack)['depth'] >= $depth) {
					array_pop($stack);
				}
				
				// If we still have items in the stack, the top is our parent
				if (!empty($stack)) {
					// Reference the top item in the stack (the parent)
					$parentIndex = count($stack) - 1;
					$stack[$parentIndex]['children'][] = $item;
					
					// Now push a reference to the newly added child
					$childIndex = count($stack[$parentIndex]['children']) - 1;
					$stack[] = &$stack[$parentIndex]['children'][$childIndex];
				} else {
					// No parent in the stack => it's a new root in this subset
					$tree[] = $item;
					
					// Push a reference to this new root onto the stack
					$rootIndex = count($tree) - 1;
					$stack[] = &$tree[$rootIndex];
				}
			}
		}
		
		return $tree;
	}
}
