<?php

namespace App\Helpers\Common\Flash;

class FlashNotifier
{
	private string $message;
	private string $title = '';
	private bool $saveNow = false;

	public function __construct(string $message)
	{
		$this->message = $message;
	}

	public function title(string $title): self
	{
		$this->title = $title;

		return $this;
	}

	public function now(): self
	{
		$this->saveNow = true;

		return $this;
	}

	public function immediate(): self
	{
		return $this->now();
	}

	public function success(?string $title = null): void
	{
		FlashMessage::success($this->message, $title ?? $this->title ?: null, $this->saveNow);
	}

	public function error(?string $title = null): void
	{
		FlashMessage::error($this->message, $title ?? $this->title ?: null, $this->saveNow);
	}

	public function warning(?string $title = null): void
	{
		FlashMessage::warning($this->message, $title ?? $this->title ?: null, $this->saveNow);
	}

	public function info(?string $title = null): void
	{
		FlashMessage::info($this->message, $title ?? $this->title ?: null, $this->saveNow);
	}

	public function default(?string $title = null): void
	{
		FlashMessage::message($this->message, $title ?? $this->title ?: null, $this->saveNow);
	}

	public function message(?string $title = null): void
	{
		$this->default($title);
	}
}
