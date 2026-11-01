<?php

namespace App\Helpers\Services;

use App\Helpers\Common\Arr;

use Jaybizzle\CrawlerDetect\CrawlerDetect;

class UrlGen
{
	// ADMIN PANEL

	public function adminUri(?string $path = ''): string
	{
		$basePath = $this->getAdminBasePath();

		$path = str_replace(url($basePath), '', $path);
		$path = ltrim($path, '/');

		if (!empty($path)) {
			$path = $basePath . '/' . $path;
		} else {
			$path = $basePath;
		}

		return castToString($path, '/');
	}

	public function adminUrl(?string $path = ''): string
	{
		return url($this->adminUri($path));
	}

	public function getAdminBasePath(): string
	{
		$basePath = 'admin';
		$basePath = config('larapen.core.basePath.admin', $basePath);

		return castToString($basePath, '/');
	}

	// FRONT

	public function home(): string
	{
		return url('/');
	}

	public function page($entry): string
	{
		$entry = is_array($entry) ? Arr::toObject($entry) : $entry;

		if (isset($entry->slug)) {
			$path = str_replace(['{slug}'], [$entry->slug], config('routes.pageBySlug'));
			$url = url($path);
		} else {
			$url = '/';
		}

		return urlBuilder($url)->toString();
	}

	public function sitemap(?string $countryCode = null): string
	{
		if (empty($countryCode)) {
			$countryCode = config('country.code');
		}

		$countryCodePath = '';
		if (isMultiCountriesUrlsEnabled()) {
			if (!empty($countryCode)) {
				$countryCodePath = strtolower($countryCode) . '/';
			}
		}

		$path = str_replace(['{countryCode}/'], [''], config('routes.sitemap'));
		$url = url($countryCodePath . $path);

		return urlBuilder($url)->toString();
	}

	public function countries(): string
	{
		$url = url(config('routes.countries'));

		if (doesCountriesPageCanBeLinkedToTheHomepage()) {
			$url = str(config('app.url'))->finish('/')->toString();

			$crawler = new CrawlerDetect;
			if (!$crawler->isCrawler()) {
				$url = $url . 'locale/' . config('app.locale');
			}
		}

		return urlBuilder($url)->toString();
	}

	public function contact(): string
	{
		return urlBuilder(config('routes.contact'))->toString();
	}
}
