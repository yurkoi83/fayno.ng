<?php

namespace App\Observers;

use App\Models\MenuItem;
use App\Concerns\HasNestedColumns;

class MenuItemObserver extends BaseObserver
{
	use HasNestedColumns;

	/**
	 * Listen to the Entry creating event.
	 *
	 * @return void
	 */
	public function creating(MenuItem $menuItem)
	{
		// Apply the nested created actions
		return $this->creatingNestedItem($menuItem);
	}

	/**
	 * Listen to the Entry updating event.
	 *
	 * @return void
	 */
	public function updating(MenuItem $menuItem)
	{
		// Apply the nested updating actions
		return $this->updatingNestedItem($menuItem);
	}

	/**
	 * Listen to the Entry deleting event.
	 *
	 * @param MenuItem $menuItem
	 * @return void
	 */
	public function deleting($menuItem)
	{
		// Apply the nested deleting actions
		$this->deletingNestedItem($menuItem);

		// Delete the category's children recursively
		$this->deleteChildrenRecursively($menuItem);
	}

	/**
	 * Listen to the Entry saved event.
	 *
	 * @return void
	 */
	public function saved(MenuItem $menuItem)
	{
		// ...
	}

	/**
	 * Listen to the Entry deleted event.
	 *
	 * @return void
	 */
	public function deleted(MenuItem $menuItem)
	{
		// ...
	}
}
