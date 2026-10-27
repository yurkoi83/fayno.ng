<?php

use App\Helpers\Common\Arr;
use App\Models\Permission;

/**
 * Checkbox Display
 *
 * @param $fieldValue
 * @param $id
 * @return string
 */
function checkboxDisplay($fieldValue, $id = null): string
{
	$attrId = !empty($id) ? 'id="' . $id . '"' : '';

	$isChecked = is_bool($fieldValue) ? $fieldValue : !empty($fieldValue);

	// fa-square-o | fa-check-square-o
	// fa-toggle-off | fa-toggle-on
	if ($isChecked) {
		return '<i ' . $attrId . ' class="admin-single-icon fa-solid fa-toggle-on" aria-hidden="true"></i>';
	} else {
		return '<i ' . $attrId . ' class="admin-single-icon fa-solid fa-toggle-off" aria-hidden="true"></i>';
	}
}

/**
 * Ajax Checkbox Display
 *
 * @param $id
 * @param $table
 * @param $field
 * @param null $fieldValue
 * @return string
 */
function ajaxCheckboxDisplay($id, $table, $field, $fieldValue = null): string
{
	$lineId = $field . $id;
	$lineId = str_replace('.', '', $lineId); // fix JS bug (in admin layout)
	$data = 'data-table="' . $table . '"
			data-field="' . $field . '"
			data-line-id="' . $lineId . '"
			data-id="' . $id . '"
			data-value="' . $fieldValue . '"';

	// Decoration
	$html = checkboxDisplay($fieldValue, $lineId);

	return '<a href="" class="ajax-request" ' . $data . '>' . $html . '</a>';
}

/**
 * Advanced Ajax Checkbox Display
 *
 * @param $id
 * @param $table
 * @param $field
 * @param null $fieldValue
 * @return string
 */
function installAjaxCheckboxDisplay($id, $table, $field, $fieldValue = null): string
{
	$lineId = $field . $id;
	$lineId = str_replace('.', '', $lineId); // fix JS bug (in admin layout)
	$data = 'data-table="' . $table . '"
			data-field="' . $field . '"
			data-line-id="' . $lineId . '"
			data-id="' . $id . '"
			data-value="' . $fieldValue . '"';

	// Decoration
	$html = checkboxDisplay($fieldValue, $lineId);
	$html = '<a href="" class="ajax-request" ' . $data . '>' . $html . '</a>';

	// Install country's decoration
	$html .= ' &nbsp;';
	if ($fieldValue == 1) {
		$html .= '<a href="" id="install' . $id . '" class="ajax-request btn btn-xs btn-success text-white" ' . $data . '>';
		$html .= '<i class="fa-solid fa-download"></i> ' . trans('admin.Installed');
		$html .= '</a>';
	} else {
		$html .= '<a href="" id="install' . $id . '" class="ajax-request btn btn-xs btn-light" ' . $data . '>';
		$html .= '<i class="fa-solid fa-download"></i> ' . trans('admin.Install');
		$html .= '</a>';
	}

	return $html;
}

/**
 * Generate the Listing's link from the Admin panel
 *
 * @param $listing
 * @return string
 */
function getListingUrl($listing): string
{
	// Get the listing's possible payment info
	$paymentInfo = '';
	if (!empty($listing->payment)) {
		$info = ' (' . $listing->payment->expiry_info . ')';
		$class = 'text-' . $listing->payment->css_class_variant;
		$planName = $listing->payment->plan?->short_name ?? trans('global.unknown_plan');

		$paymentInfo = ' <i class="fa-solid fa-circle-check ' . $class . '"
                    data-bs-placement="bottom" data-bs-toggle="tooltip"
                    type="button" title="' . $planName . $info . '">
                </i>';
	}

	// Get the listing link
	if (!is_null($listing) && isset($listing->country_code, $listing->title)) {
		$url = dmUrl($listing->country_code, urlGen()->listingPath($listing));
		$out = linkStrLimit($url, $listing->title, 35, 'target="_blank"') . $paymentInfo;
	} else {
		$out = '--';
	}

	return $out;
}

/**
 * @param $entry
 * @param bool $withLink
 * @return string
 */
function getCountryFlag($entry, bool $withLink = false): string
{
	$out = '';

	$country = $entry->country ?? null;
	$countryCode = $country->code ?? $entry->country_code ?? null;

	if (!empty($countryCode)) {
		$countryName = $country->name ?? $countryCode;
		$countryFlagUrl = $country->flag_url ?? $entry->country_flag_url ?? null;

		if (!empty($countryFlagUrl)) {
			$out = ($withLink) ? '<a href="' . dmUrl($countryCode, '/', true, true) . '" target="_blank">' : '';
			$out .= '<img src="' . $countryFlagUrl . '" data-bs-toggle="tooltip" title="' . $countryName . '">';
			$out .= ($withLink) ? '</a>' : '';
			$out .= ' ';
		} else {
			$out .= $countryCode . ' ';
		}
	}

	return $out;
}

/**
 * Check if the Listing is verified
 *
 * @param $listing
 * @return bool
 */
function isVerifiedListing($listing): bool
{
	$listing = (is_array($listing)) ? Arr::toObject($listing) : $listing;

	if (
		!Arr::keyExists('email_verified_at', $listing)
		|| !Arr::keyExists('phone_verified_at', $listing)
		|| !Arr::keyExists('reviewed_at', $listing)
	) {
		return false;
	}

	if (listingsNeedToBeReviewed()) {
		$verified = (!empty($listing->email_verified_at) && !empty($listing->phone_verified_at) && !empty($listing->reviewed_at));
	} else {
		$verified = (!empty($listing->email_verified_at) && !empty($listing->phone_verified_at));
	}

	return $verified;
}

/**
 * Check if the User is verified
 *
 * @param $user
 * @return bool
 */
function isVerifiedUser($user): bool
{
	$user = (is_array($user)) ? Arr::toObject($user) : $user;

	if (!Arr::keyExists('email_verified_at', $user) || !Arr::keyExists('phone_verified_at', $user)) {
		return false;
	}

	return (!empty($user->email_verified_at) && !empty($user->phone_verified_at));
}

/**
 * @return bool
 */
function userHasAdminPermissions(): bool
{
	if (auth()->check()) {
		$permissions = Permission::getAdminPermissions();

		// Remove the standard staff permissions
		$permissions = collect($permissions)
			->reject(fn ($item) => in_array($item, Permission::getStaffPermissions()))
			->toArray();

		// Check if user has the admin permissions
		if (doesUserHavePermission(auth()->user(), $permissions)) {
			return true;
		}
	}

	return false;
}

