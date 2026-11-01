<?php

namespace App\Helpers\Common\HierarchicalData\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/*
 * Example Usage
 *
 * Create a hierarchy
 * $electronics = Category::create(['name' => 'Electronics']);
 * $laptops = Category::create(['name' => 'Laptops', 'parent_id' => $electronics->id]);
 * $smartphones = Category::create(['name' => 'Smartphones', 'parent_id' => $electronics->id]);
 *
 * Get descendants
 * $allElectronicsDescendants = $electronics->getDescendants();
 *
 * Get ancestors
 * $laptopAncestors = $laptops->getAncestors();
 */

trait HasAdjacentList
{
	protected string $parentColumn = 'parent_id';
	
	/**
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
	 */
	public function parent(): BelongsTo
	{
		return $this->belongsTo(self::class, $this->parentColumn);
	}
	
	/**
	 * @return \Illuminate\Database\Eloquent\Relations\HasMany
	 */
	public function children(): HasMany
	{
		return $this->hasMany(self::class, $this->parentColumn);
	}
	
	/**
	 * @return \Illuminate\Database\Eloquent\Relations\HasMany
	 */
	public function allChildren(): HasMany
	{
		return $this->children()->with('allChildren');
	}
	
	/**
	 * @return bool
	 */
	public function isRoot(): bool
	{
		return $this->{$this->parentColumn} === null;
	}
	
	/**
	 * @param $parentId
	 * @return bool
	 */
	public function isChildOf($parentId): bool
	{
		return $this->{$this->parentColumn} === $parentId;
	}
	
	/**
	 * @return \Illuminate\Support\Collection
	 */
	public function getAncestors(): Collection
	{
		$ancestors = collect();
		$current = $this;
		
		while ($current->parent) {
			$ancestors->push($current->parent);
			$current = $current->parent;
		}
		
		return $ancestors->reverse();
	}
	
	/**
	 * @return \Illuminate\Support\Collection
	 */
	public function getDescendants(): Collection
	{
		$descendants = collect();
		
		if (!isset($this->children)) return $descendants;
		
		foreach ($this->children as $child) {
			$descendants->push($child);
			$descendants = $descendants->merge($child->getDescendants());
		}
		
		return $descendants;
	}
}
