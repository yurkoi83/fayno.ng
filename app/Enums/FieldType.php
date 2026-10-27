<?php

namespace App\Enums;

use App\Helpers\Common\VideoEmbedder;

enum FieldType: string
{
	use EnumToArray;

	case Text = 'text';
	case Textarea = 'textarea';
	case Checkbox = 'checkbox';
	case CheckboxMultiple = 'checkbox_multiple';
	case Select = 'select';
	case Radio = 'radio';
	case File = 'file';
	case Image = 'image';
	case Url = 'url';
	case Video = 'video';
	case Number = 'number';
	case Date = 'date';
	case DateTime = 'date_time';
	case DateRange = 'date_range';

	/**
	 * Human label shown in the admin "Type" dropdown.
	 */
	public function label(): string
	{
		return match ($this) {
			self::Text             => 'Text',
			self::Textarea         => 'Textarea',
			self::Checkbox         => 'Checkbox',
			self::CheckboxMultiple => 'Checkbox (Multiple)',
			self::Select           => 'Select Box',
			self::Radio            => 'Radio',
			self::File             => 'File',
			self::Image            => 'Image',
			self::Url              => 'URL',
			self::Video            => 'Video URL ' . VideoEmbedder::getPlatforms(),
			self::Number           => 'Number',
			self::Date             => 'Date',
			self::DateTime         => 'Date Time',
			self::DateRange        => 'Date Range',
		};
	}

	/**
	 * [value => label] map (declaration order) used to build the <select> options.
	 *
	 * @return array<string, string>
	 */
	public static function toLabels(): array
	{
		return collect(self::cases())
			->mapWithKeys(fn (self $type) => [$type->value => $type->label()])
			->all();
	}

	/**
	 * Field types that hold a list of options (select / radio / checkbox group).
	 *
	 * @return array<int, self>
	 */
	public static function withOptions(): array
	{
		return [self::Select, self::Radio, self::CheckboxMultiple];
	}

	/**
	 * Backing values of the option-based field types.
	 *
	 * @return array<int, string>
	 */
	public static function optionValues(): array
	{
		return array_map(fn (self $type) => $type->value, self::withOptions());
	}

	/**
	 * Whether this type holds a list of options.
	 */
	public function hasOptions(): bool
	{
		return in_array($this, self::withOptions(), true);
	}

	/**
	 * Field types whose value is an uploaded file stored on disk.
	 *
	 * @return array<int, self>
	 */
	public static function uploads(): array
	{
		return [self::File, self::Image];
	}

	/**
	 * Backing values of the upload-based field types.
	 *
	 * @return array<int, string>
	 */
	public static function uploadValues(): array
	{
		return array_map(fn (self $type) => $type->value, self::uploads());
	}

	/**
	 * Whether this type stores an uploaded file on disk.
	 */
	public function isUpload(): bool
	{
		return in_array($this, self::uploads(), true);
	}

	/**
	 * Types whose `max` column means a character / value length
	 * (the admin "Max length" input applies to these).
	 *
	 * @return array<int, string>
	 */
	public static function maxLengthValues(): array
	{
		return [self::Text->value, self::Textarea->value, self::Number->value];
	}

	/**
	 * Types that cannot be used as a search filter (the admin "Use as filter"
	 * switch is hidden for these — they hold files or embedded media).
	 *
	 * @return array<int, string>
	 */
	public static function nonFilterableValues(): array
	{
		return [self::File->value, self::Image->value, self::Video->value];
	}
}
