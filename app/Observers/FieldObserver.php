<?php

namespace App\Observers;

use App\Enums\FieldType;
use App\Helpers\Common\Files\Storage\StorageDisk;
use App\Models\Field;
use App\Models\FieldOption;

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
		// Delete all the Custom Field's options
		$fieldOptions = FieldOption::where('field_id', $field->id)->get();
		if ($fieldOptions->count() > 0) {
			foreach ($fieldOptions as $fieldOption) {
				$fieldOption->delete();
			}
		}
	}
}
