<?php

namespace App\Exceptions\Handler\Traits;

trait ExceptionTrait
{
	/**
	 * Get a theme error view (dot-separated) path
	 *
	 * @param string|null $viewName
	 * @return string|null
	 */
	protected function getThemeErrorViewPath(?string $viewName = null): ?string
	{
		/*
		 * Set default theme errors views directory in the possible views base directories array
		 *
		 * IMPORTANT:
		 * - The custom "views/errors/" (i.e., errors) directory that auto-discovers errors views
		 *   by their status code should not be added as an errors directory path below.
		 *
		 * DETAILS:
		 * - HTTP error views located in /resources/views/errors/ cannot be manually rendered using
		 *   response()->view() or view(). They are exclusively handled by Laravel’s core (even if their design is customized).
		 *
		 * - To manually render customized HTTP error views (e.g., 404, 503, etc.), place them in a different directory,
		 *   such as /resources/views/front/errors/. From there, they can be rendered using response()->view() or view().
		 *
		 * - Custom error views with non-standard names (e.g., custom.blade.php) work normally within /resources/views/errors/
		 *   These can be handled manually without restrictions since they don't conflict with Laravel's HTTP error handling conventions.
		 */
		$viewPathDirs = [
			'errors',
		];
		
		// Add Laravel's system error directory "views/errors/" only for non-HTTP error views (e.g., 'custom').
		if ($viewName == 'custom') {
			$viewPathDirs[] = 'errors';
		}
		
		// Use the first view found
		$viewPath = null;
		foreach ($viewPathDirs as $viewPathDir) {
			$tmpViewPath = "{$viewPathDir}.{$viewName}";
			if (view()->exists($tmpViewPath)) {
				$viewPath = $tmpViewPath;
				break;
			}
		}
		
		return $viewPath;
	}
}
