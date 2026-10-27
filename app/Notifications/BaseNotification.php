<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Twilio\TwilioChannel;

/*
 * Note: The protected methods of this class are custom methods.
 *       i.e. Not available in the Laravel core code.
 */

abstract class BaseNotification extends Notification implements ShouldQueue
{
	use Queueable;

	/**
	 * Get the notification's delivery channels.
	 *
	 * @param $notifiable
	 * @return array<int, string>
	 */
	public function via($notifiable): array
	{
		if (!$this->baseShouldSendNotificationWhen($notifiable)) {
			return [];
		}

		return $this->determineViaChannels($notifiable);
	}

	/**
	 * Determine which queues should be used for each notification channel.
	 *
	 * @return array<string, string>
	 */
	public function viaQueues(): array
	{
		return [
			'mail'               => 'mail',
			'vonage'             => 'sms',
			TwilioChannel::class => 'sms',
		];
	}

	// CUSTOM METHODS
	// PROTECTED

	/**
	 * Check if the notification should be sent.
	 *
	 * @param $notifiable
	 * @return bool
	 */
	protected function shouldSendNotificationWhen($notifiable): bool
	{
		return true;
	}

	/**
	 * Define channels in derived classes.
	 *
	 * @param $notifiable
	 * @return array<int, string>
	 */
	abstract protected function determineViaChannels($notifiable): array;

	// PRIVATE

	/**
	 * Base (global) method to check if the notification should be sent.
	 *
	 * @param $notifiable
	 * @return bool
	 */
	private function baseShouldSendNotificationWhen($notifiable): bool
	{
		$recipientEmail = $this->getRouteNotificationForMail($notifiable);

		// Ensure the notification is only sent if the email is not a demo email address
		if ($this->isDemoEmailRecipient($recipientEmail)) {
			logger()->info('Notification to "' . $recipientEmail . '" was blocked (for demo staff address).');

			return false;
		}

		// Ensure the notification is only sent if the notifiable is not related to a demo fake user
		if ($this->isDemoFakeRecipient($notifiable)) {
			logger()->info('Notification to a "demoFaker" user was blocked (for demo fake address).');

			return false;
		}

		/*
		 * Return true if the shouldSendNotificationWhen() does not exist or it if exists
		 *
		 * NOTE: The shouldSendNotificationWhen(), is a custom method to check if the
		 * notification should be sent. Not to be confused with the Laravel core shouldSend() method
		 * that determining if a queued notification should be sent. Also, not to be confused
		 * with the Laravel core shouldSendNotification() that call shouldSend() method
		 */

		return (
			!method_exists($notifiable, 'shouldSendNotificationWhen')
			|| $this->shouldSendNotificationWhen($notifiable)
		);
	}

	/**
	 * Ensure the notification is only sent if the email is not a demo email address
	 *
	 * @param string|null $email
	 * @return bool
	 */
	private function isDemoEmailRecipient(?string $email): bool
	{
		if (!isDemoEnv()) return false;

		return isDemoEmailAddress($email);
	}

	/**
	 * Ensure the notification is only sent if the notifiable is not related to a demo fake user
	 *
	 * @param $notifiable
	 * @return bool
	 */
	private function isDemoFakeRecipient($notifiable): bool
	{
		if (!isDemoEnv()) return false;

		if (is_object($notifiable) && isset($notifiable->phone_token)) {
			return ($notifiable->phone_token == 'demoFaker');
		}

		if (is_array($notifiable) && isset($notifiable['phone_token'])) {
			return ($notifiable['phone_token'] == 'demoFaker');
		}

		return false;
	}

	/**
	 * Get the recipient email address
	 *
	 * @param $notifiable
	 * @return string|null
	 */
	private function getRouteNotificationForMail($notifiable): ?string
	{
		if (!is_object($notifiable) && !is_array($notifiable)) return null;

		if (is_array($notifiable)) {
			return !empty($notifiable['email']) ? $notifiable['email'] : null;
		}

		// Get the recipient email address from the Laravel's routeNotificationForMail() method
		$recipientEmail = method_exists($notifiable, 'routeNotificationForMail')
			? $notifiable->routeNotificationForMail()
			: null;

		if (is_array($recipientEmail)) {
			$recipientEmail = array_keys($recipientEmail)[0];
			$recipientEmail = is_string($recipientEmail) ? $recipientEmail : null;
		}

		if (empty($recipientEmail)) {
			// Get the recipient email address from the custom's getEmailForNotification() method
			$recipientEmail = method_exists($notifiable, 'getEmailForNotification')
				? $notifiable->getEmailForNotification()
				: null;
		}

		if (empty($recipientEmail)) {
			$recipientEmail = !empty($notifiable->email) ? $notifiable->email : null;
		}

		return castToStringOrNull($recipientEmail);
	}
}
