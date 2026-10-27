<?php
/*
 * LaraClassifier - Classified Ads Web Application
 * Copyright (c) BeDigit. All Rights Reserved
 *
 * Website: https://bedigit.com
 * Author: Mayeul Akpovi (BeDigit)
 *
 * LICENSE
 * -------
 * This software is provided under a license agreement and may only be used or copied
 * in accordance with its terms, including the inclusion of the above copyright notice.
 * As this software is sold exclusively on CodeCanyon,
 * please review the full license details here: https://codecanyon.net/licenses/standard
 */

namespace App\Observers;

use App\Enums\FieldType;
use App\Helpers\Common\Files\Storage\StorageDisk;
use App\Models\CategoryField;
use App\Models\Field;
use App\Models\FieldOption;
use App\Models\ListingValue;

class FieldObserver extends BaseObserver
{
	/**
	 * Listen to the Entry updating event.
	 *
	 * @param Field $field
	 * @return void
	 */
	public function updating(Field $field)
	{
		// Get the original object values
		$original = $field->getOriginal();
		
		// Get the original field type value
		$originalFieldType = $original['type'] ?? null;
		
		// Check if the field type changed
		$isFieldTypeChanged = (!empty($originalFieldType) && !empty($field->type) && $originalFieldType != $field->type);
		if ($isFieldTypeChanged) {
			// Storage Disk Init.
			$disk = StorageDisk::getDisk();
			
			// Get fields types having options or file upload
			$fieldTypesHavingOptions = FieldType::optionValues();
			
			// Check if field type has changed from type with options to type without options
			$isFieldTypeChangedToFieldWithoutOptions = (
				in_array($originalFieldType, $fieldTypesHavingOptions)
				&& !in_array($field->type, $fieldTypesHavingOptions)
			);
			
			if ($isFieldTypeChangedToFieldWithoutOptions) {
				
				// Delete all the Custom Field's options
				$options = FieldOption::where('field_id', $field->id);
				if ($options->count() > 0) {
					foreach ($options->cursor() as $option) {
						$option->delete();
					}
				}
				
				// Delete all Listings Custom Field's Values
				$listingValues = ListingValue::where('field_id', $field->id)->get();
				if ($listingValues->count() > 0) {
					foreach ($listingValues as $listingValue) {
						$listingValue->delete();
					}
				}
				
			} else {
				
				// Delete all Listings Custom Field's Values
				$listingValues = ListingValue::where('field_id', $field->id)->get();
				if ($listingValues->count() > 0) {
					foreach ($listingValues as $listingValue) {
						// If field is an upload type ('file' or 'image'), remove files (if exists)
						if (in_array($field->type, FieldType::uploadValues())) {
							if (!empty($listingValue->value)) {
								if ($disk->exists($listingValue->value)) {
									$disk->delete($listingValue->value);
								}
							}
						}
						// Delete the Listing's value for this field
						$listingValue->delete();
					}
				}
				
			}
		}
	}
	
	/**
	 * Listen to the Entry deleting event.
	 *
	 * @param Field $field
	 * @return void
	 */
	public function deleting($field)
	{
		// Delete all Categories Custom Fields
		$catFields = CategoryField::where('field_id', $field->id)->get();
		if ($catFields->count() > 0) {
			foreach ($catFields as $catField) {
				$catField->delete();
			}
		}
		
		// Delete all the Custom Field's options
		$fieldOptions = FieldOption::where('field_id', $field->id)->get();
		if ($fieldOptions->count() > 0) {
			foreach ($fieldOptions as $fieldOption) {
				$fieldOption->delete();
			}
		}
		
		// Delete all Listings Custom Field's Values
		$listingValues = ListingValue::where('field_id', $field->id)->get();
		if ($listingValues->count() > 0) {
			foreach ($listingValues as $listingValue) {
				$listingValue->delete();
			}
		}
	}
}
