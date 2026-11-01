<?php

namespace App\Observers;

use App\Models\Menu;

class MenuObserver extends BaseObserver
{
	/**
	 * Listen to the Entry saved event.
	 *
	 * @param Menu $menu
	 * @return void
	 */
	public function saved(Menu $menu)
	{
		// ...
	}

	/**
	 * Listen to the Entry deleted event.
	 *
	 * @param Menu $menu
	 * @return void
	 */
	public function deleted(Menu $menu)
	{
		// ...
	}
}
