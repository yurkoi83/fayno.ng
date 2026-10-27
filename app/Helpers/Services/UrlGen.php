<?php

namespace App\Helpers\Services;

use App\Helpers\Common\Arr;
use App\Helpers\Services\UrlGen\SearchTrait;
use App\Http\Controllers\Web\Auth\Traits\Custom\UrlGen\AccountUrlGen;
use App\Http\Controllers\Web\Auth\Traits\Custom\UrlGen\AuthUrlGen;
use Jaybizzle\CrawlerDetect\CrawlerDetect;

class UrlGen
{
	use AccountUrlGen;
	use AuthUrlGen;
	use SearchTrait;

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

	public function listingPath($entry, bool $encoded = false): string
	{
		$entry = is_array($entry) ? Arr::toObject($entry) : $entry;

		if (isset($entry->id) && isset($entry->title)) {
			$preview = !isVerifiedListing($entry) ? '?preview=1' : '';

			$slug = ($encoded) ? rawurlencode($entry->slug) : $entry->slug;

			$path = str_replace(['{slug}', '{hashableId}', '{id}'], [$slug, hashId($entry->id), $entry->id], config('routes.listing'));
			$path = $path . $preview;
		} else {
			$path = '/';
		}

		return castToString($path);
	}

	public function listingPathBasic($id, string $slug = 'listing-slug'): string
	{
		$path = str_replace(['{slug}', '{hashableId}', '{id}'], [$slug, $id, $id], config('routes.listing'));

		return castToString($path);
	}

	public function listingUri($entry, bool $encoded = false): string
	{
		return $this->listingPath($entry, $encoded);
	}

	public function listing($entry, bool $encoded = false): string
	{
		$entry = is_array($entry) ? Arr::toObject($entry) : $entry;

		if (addon_active('domainmapping')) {
			$url = dmUrl($entry->country_code, $this->listingUri($entry, $encoded));
		} else {
			$url = url($this->listingPath($entry, $encoded));
		}

		return urlBuilder($url)->toString();
	}

	public function listingPathPattern(string $id, string $slug): string
	{
		$search = ['{slug}', '{hashableId}', '{id}'];
		$replace = [$slug, $id, $id];
		$subject = config('routes.listing', '#entrySlug/#entryId');
		$nextUriPath = str_replace($search, $replace, $subject);

		return castToString($nextUriPath, '/');
	}

	public function listingPattern(string $id, string $slug): string
	{
		return url($this->listingPathPattern($id, $slug));
	}

	public function reportListing($entry): string
	{
		$entityId = is_numeric($entry) ? $entry : data_get($entry, 'id');

		if (!empty($entityId)) {
			$url = url('listings/' . hashId($entityId) . '/report');
		} else {
			$url = url('/');
		}

		return urlBuilder($url)->toString();
	}

	public function addListing(): string
	{
		return urlBuilder(url('listings/create'))
			->setParameters(request()->only(['planId']))
			->toString();
	}

	public function addListingDetails(): string
	{
		return urlBuilder(url('listings/create/details'))
			->setParameters(request()->only(['planId']))
			->toString();
	}

	public function addListingPhotos(): string
	{
		return urlBuilder(url('listings/create/photos'))
			->setParameters(request()->only(['planId']))
			->toString();
	}

	public function addListingPayment(): string
	{
		return urlBuilder(url('listings/create/payment'))
			->setParameters(request()->only(['planId']))
			->toString();
	}

	public function addListingPaymentSuccess(): string
	{
		return url('listings/create/payment/success');
	}

	public function addListingPaymentCancel(): string
	{
		return url('listings/create/payment/cancel');
	}

	public function addListingFinished(): string
	{
		return url('listings/create/finish');
	}

	public function editListing($entry): string
	{
		return $this->editListingDetails($entry);
	}

	public function editListingDetails($entry): string
	{
		$entityId = isStringableStrict($entry) ? $entry : data_get($entry, 'id');

		$url = !empty($entityId) ? url("listings/{$entityId}/details") : '/';

		return urlBuilder($url)
			->setParameters(request()->only(['planId']))
			->toString();
	}

	public function editListingPhotos($entry): string
	{
		$entityId = isStringableStrict($entry) ? $entry : data_get($entry, 'id');

		$url = !empty($entityId) ? url("listings/{$entityId}/photos") : '/';

		return urlBuilder($url)->toString();
	}

	public function editListingPayment($entry): string
	{
		$entityId = isStringableStrict($entry) ? $entry : data_get($entry, 'id');

		$url = !empty($entityId) ? url("listings/{$entityId}/payment") : '/';

		return urlBuilder($url)->toString();
	}

	public function editListingPaymentSuccess($entry): string
	{
		$entityId = isStringableStrict($entry) ? $entry : data_get($entry, 'id');

		return url("listings/{$entityId}/payment/success");
	}

	public function editListingPaymentCancel($entry): string
	{
		$entityId = isStringableStrict($entry) ? $entry : data_get($entry, 'id');

		return url("listings/{$entityId}/payment/cancel");
	}

	public function companies(?string $countryCode = null): string
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

		$path = str_replace(['{countryCode}/'], [''], config('routes.companies'));
		$url = url($countryCodePath . $path);

		return urlBuilder($url)->toString();
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

	public function pricing(): string
	{
		return urlBuilder(config('routes.pricing'))->toString();
	}
}
