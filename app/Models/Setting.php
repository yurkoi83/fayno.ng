<?php

namespace App\Models;

use App\Helpers\Common\CacheManager\InvalidatesCache;
use App\Models\Traits\SettingTrait;
use App\Observers\SettingObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Flat key/value setting row.
 *
 * One row = one setting. Reads go through SettingService::get('group.inner_key');
 * direct Eloquent reads of this model are reserved for the admin2 settings editor.
 *
 * `value` is JSON-cast so it can hold scalars or arrays transparently. Saves auto-bust
 * the per-process cache via InvalidatesCache (inherited Setting::class bucket).
 */
#[ObservedBy([SettingObserver::class])]
class Setting extends Model
{
	use InvalidatesCache;
	use SettingTrait;

	protected $fillable = [
		'group',
		'key',
		'value',
	];

	protected function casts(): array
	{
		return [
			'value' => 'json',
		];
	}

	/**
	 * Filter rows to a single group (e.g. 'app', 'mail').
	 */
	public function scopeGroup(Builder $query, string $group): Builder
	{
		return $query->where('group', $group);
	}

	/**
	 * Direct getter — bypasses SettingService cache. Use setting() in app code instead.
	 *
	 * Looks up by the globally-unique `key` column. Post-Stage-4 keys are flat-prefixed
	 * (`app_email`, `mail_driver`, …) so no `group` argument is needed for disambiguation.
	 */
	public static function getValue(string $key, mixed $default = null): mixed
	{
		$row = static::query()->where('key', $key)->first();

		return $row?->value ?? $default;
	}

	/**
	 * Direct setter — writes a single flat row, upserting on `key` conflict.
	 *
	 * `$group` is stored on the row for UI organization (sidebar tab) but plays no
	 * role in row identity. Triggers InvalidatesCache on save, so SettingService::all()
	 * refreshes on the next read.
	 */
	public static function setValue(string $key, mixed $value, string $group = 'general'): self
	{
		return static::query()->updateOrCreate(
			['key' => $key],
			['value' => $value, 'group' => $group],
		);
	}
}
