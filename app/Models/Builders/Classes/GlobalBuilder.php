<?php

namespace App\Models\Builders\Classes;

use Illuminate\Database\Eloquent\Builder;

class GlobalBuilder extends Builder
{
	/**
	 * @return $this
	 */
	public function columnIsEmpty(string $column): static
	{
		$this->where(function (self $query) use ($column) {
			$query->where($column, '')->orWhere($column, 0)->orWhereNull($column);
		});

		return $this;
	}

	/**
	 * @return $this
	 */
	public function columnIsNotEmpty(string $column): static
	{
		$this->where(function (self $query) use ($column) {
			$query->where($column, '!=', '')->where($column, '!=', 0)->whereNotNull($column);
		});

		return $this;
	}

	/**
	 * @return $this
	 */
	public function orColumnIsEmpty(string $column): static
	{
		$this->orWhere(fn (self $query) => $query->columnIsEmpty($column));

		return $this;
	}

	/**
	 * @return $this
	 */
	public function orColumnIsNotEmpty(string $column): static
	{
		$this->orWhere(fn (self $query) => $query->columnIsNotEmpty($column));

		return $this;
	}
}
