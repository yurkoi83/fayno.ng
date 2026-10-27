<?php

namespace App\Exceptions\Handler\Addon;

use Throwable;

trait FixFolderName
{
	/**
	 * Try to fix the broken addons installation
	 * Try to fix the addon folder name issue
	 */
	public function tryToFixAddonDirName($message): ?string
	{
		if (empty($message)) {
			return null;
		}
		
		// Get the broken addon name
		$matches = [];
		preg_match('|/extensions/addons/([^/]+)/|ui', $message, $matches);
		$brokenAddonName = $matches[1] ?? null;
		
		if (empty($brokenAddonName)) {
			return null;
		}
		
		$issueFixed = false;
		$addonsBasePath = base_path('extensions/addons') . '/';
		
		// Load all the addons' services provider
		$addonsFoldersNames = [];
		try {
			$addonsFoldersNames = scandir($addonsBasePath);
			$addonsFoldersNames = array_diff($addonsFoldersNames, ['..', '.']);
		} catch (Throwable $e) {
		}
		
		if (empty($addonsFoldersNames)) {
			return null;
		}
		
		foreach ($addonsFoldersNames as $addonFolder) {
			// Look for the ServiceProvider in both layouts:
			// - Legacy:    extensions/addons/<name>/<X>ServiceProvider.php
			// - New style: extensions/addons/<name>/src/<X>ServiceProvider.php
			$spFiles = array_merge(
				glob($addonsBasePath . $addonFolder . '/*ServiceProvider.php') ?: [],
				glob($addonsBasePath . $addonFolder . '/src/*ServiceProvider.php') ?: []
			);
			foreach ($spFiles as $spFilePath) {
				$matches = [];
				preg_match('|/extensions/addons/([^/]+)/(?:src/)?([a-zA-Z0-9]+)ServiceProvider|ui', $spFilePath, $matches);
				if (empty($matches[1]) || empty($matches[2])) {
					continue;
				}
				
				$folderName = $matches[1];
				$addonName = strtolower($matches[2]);
				if ($folderName == $addonName) {
					continue;
				}
				
				$nsFolderName = $this->getFolderFromTheServiceProviderContent($spFilePath);
				if ($folderName == $nsFolderName) {
					continue;
				}
				
				$oldFolderPath = $addonsBasePath . $addonFolder;
				$newFolderPath = $addonsBasePath . $addonName;
				
				// Continue if the new folder name already exists
				if (file_exists($newFolderPath)) {
					continue;
				}
				
				// Renames the broken addon directory
				try {
					if (is_dir($oldFolderPath)) {
						rename($oldFolderPath, $newFolderPath);
						$issueFixed = true;
					}
				} catch (Throwable $e) {
				}
			}
		}
		
		if ($issueFixed) {
			// Customize and Redirect to the previous URL
			$previousUrl = url()->previous();
			$baseUrl = url('/');
			
			// Check if redirection is allowed
			// That avoids infinite redirections and redirections to external URLs
			$isRedirectionAllowed = (
				request()->input('addonsFolderFixedBy') != $brokenAddonName
				&& str_starts_with($previousUrl, $baseUrl)
			);
			
			if ($isRedirectionAllowed) {
				// Customize and Redirect to the previous URL
				$previousUrl = url()->previous();
				
				// Add the addon name to query parameters
				$previousUrl = urlBuilder($previousUrl)
					->setParameters(['addonsFolderFixedBy' => $brokenAddonName])
					->toString();
				
				// Redirect
				redirectUrl($previousUrl, 301, config('larapen.core.noCacheHeaders'));
			} else {
				$errorMessage = 'The "<code>%s</code>" addon was broken due to the name of the folder that contains it.
				The script tried to fix this issue... By refreshing this page, the issue should be resolved.
				If it is not the case, please reread the documentation on the installation of this addon to fix the issue manually.';
				
				return sprintf($errorMessage, $brokenAddonName);
			}
		}
		
		return null;
	}
	
	// PRIVATE
	
	private function getFolderFromTheServiceProviderContent($path): ?string
	{
		if (!file_exists($path)) {
			return null;
		}
		
		try {
			$content = file_get_contents($path);
			
			// Legacy layout: namespace extensions\addons\<name>;
			$matches = [];
			preg_match('|namespace\s+extensions\\\addons\\\([A-Za-z0-9_]+)|ui', $content, $matches);
			$nsFolderName = (!empty($matches[1])) ? trim($matches[1]) : null;
			
			if (!empty($nsFolderName)) {
				return $nsFolderName;
			}
			
			// New-style layout: namespace Addons\<StudlyName>;
			// Folder names in this codebase are all lowercase with no separators,
			// so the canonical reverse map is strtolower($studly).
			$matches = [];
			preg_match('|namespace\s+Addons\\\([A-Za-z0-9_]+)|u', $content, $matches);
			$nsStudly = (!empty($matches[1])) ? trim($matches[1]) : null;
			
			if (!empty($nsStudly)) {
				return strtolower($nsStudly);
			}
			
			return null;
		} catch (Throwable $e) {
		}
		
		return null;
	}
}
