<?php

namespace App\Helpers\Common\Flash;

class FlashMessage
{
	const SUCCESS = 'success';
	const ERROR = 'error';
	const WARNING = 'warning';
	const INFO = 'info';
	const DEFAULT = 'default';

	/**
	 * PNotify type mapping for message levels
	 */
	private static array $pnotifyTypes = [
		self::SUCCESS => 'success',
		self::ERROR   => 'error',
		self::WARNING => 'notice',
		self::INFO    => 'info',
		self::DEFAULT => 'info',
	];

	/**
	 * PNotify icon mapping for message levels
	 */
	private static array $pnotifyIcons = [
		self::SUCCESS => 'fas fa-check-circle',
		self::ERROR   => 'fas fa-exclamation-triangle',
		self::WARNING => 'fas fa-exclamation-triangle',
		self::INFO    => 'fas fa-info-circle',
		self::DEFAULT => 'fas fa-comment',
	];

	/**
	 * Bootstrap class mapping for message levels
	 */
	private static array $bootstrapClasses = [
		self::SUCCESS => 'success',
		self::ERROR   => 'danger',
		self::WARNING => 'warning',
		self::INFO    => 'info',
		self::DEFAULT => 'secondary',
	];

	/**
	 * SweetAlert2 icon mapping for message levels
	 */
	private static array $sweetAlertIcons = [
		self::SUCCESS => 'success',
		self::ERROR   => 'error',
		self::WARNING => 'warning',
		self::INFO    => 'info',
		self::DEFAULT => 'question',
	];

	/**
	 * SweetAlert2 color mapping for message levels
	 */
	private static array $sweetAlertColors = [
		self::SUCCESS => '#28a745',
		self::ERROR   => '#dc3545',
		self::WARNING => '#ffc107',
		self::INFO    => '#17a2b8',
		self::DEFAULT => '#6c757d',
	];

	/**
	 * Add a flash message
	 */
	public static function add(string $message, string $level = self::DEFAULT, ?string $title = null, bool $saveNow = false): void
	{
		$presenter = config('larapen.flash.default');

		$flashMessage = [
			'message'   => $message,
			'level'     => $level,
			'title'     => $title ?? null,
			'id'        => 'flash-' . uniqid(),
			'timestamp' => now()->toDateTimeString(),
		];

		if (in_array($presenter, ['bsmodal', 'bstoast'])) {
			$flashMessage['bsClass'] = self::$bootstrapClasses[$level] ?? self::$bootstrapClasses[self::DEFAULT];
		}

		if ($presenter === 'pnotify') {
			$flashMessage['type'] = self::$pnotifyTypes[$level] ?? self::$pnotifyTypes[self::DEFAULT];
			$flashMessage['icon'] = self::$pnotifyIcons[$level] ?? self::$pnotifyIcons[self::DEFAULT];
		}

		if ($presenter === 'sweetalert2') {
			$flashMessage['icon'] = self::$sweetAlertIcons[$level] ?? self::$sweetAlertIcons[self::DEFAULT];
			$flashMessage['color'] = self::$sweetAlertColors[$level] ?? self::$sweetAlertColors[self::DEFAULT];
		}

		// Retrieve saved messages
		$savedFlashMessages = session('flash_messages', []);
		//dd($savedFlashMessages);
		$savedFlashMessages = is_array($savedFlashMessages) ? $savedFlashMessages : [];

		// Add the new message to the saved messages & get new message list
		// $flashMessages = $savedFlashMessages + [$flashMessage];
		$flashMessages = array_merge($savedFlashMessages, [$flashMessage]);

		// Save the new message list
		if ($saveNow) {
			// Usage of built-in Laravel (Higher memory usage & Slower)
			// session()->now('flash_messages', $flashMessages);

			// Usage of view()->share() (Lower memory usage & Faster)
			$currentImmediateMessages = view()->shared('immediateMessages', []);
			$flashMessages = array_merge($currentImmediateMessages, $flashMessages);
			view()->share('immediateMessages', $flashMessages);
		} else {
			session()->flash('flash_messages', $flashMessages);
		}
	}

	/**
	 * Add success message
	 */
	public static function success(string $message, ?string $title = null, bool $saveNow = false): void
	{
		self::add($message, self::SUCCESS, $title, $saveNow);
	}

	/**
	 * Add error message
	 */
	public static function error(string $message, ?string $title = null, bool $saveNow = false): void
	{
		self::add($message, self::ERROR, $title, $saveNow);
	}

	/**
	 * Add warning message
	 */
	public static function warning(string $message, ?string $title = null, bool $saveNow = false): void
	{
		self::add($message, self::WARNING, $title, $saveNow);
	}

	/**
	 * Add info message
	 */
	public static function info(string $message, ?string $title = null, bool $saveNow = false): void
	{
		self::add($message, self::INFO, $title, $saveNow);
	}

	/**
	 * Add default message
	 */
	public static function message(string $message, ?string $title = null, bool $saveNow = false): void
	{
		self::add($message, self::DEFAULT, $title, $saveNow);
	}

	/**
	 * Get all flash messages
	 */
	public static function all(): array
	{
		return session('flash_messages', []);
	}

	/**
	 * Clear all flash messages
	 */
	public static function clear(): void
	{
		session()->forget('flash_messages');
	}

	/**
	 * Check if there are any flash messages
	 */
	public static function has(): bool
	{
		return !empty(self::all());
	}

	/**
	 * Get flash messages by level
	 */
	public static function getByLevel(string $level): array
	{
		return array_filter(self::all(), fn ($message) => $message['level'] === $level);
	}
}
