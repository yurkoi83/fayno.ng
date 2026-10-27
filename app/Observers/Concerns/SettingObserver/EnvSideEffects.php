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

declare(strict_types=1);

namespace App\Observers\Concerns\SettingObserver;

use App\Helpers\Common\DotenvEditor;
use App\Models\Setting;

/**
 * `.env` write-through for settings whose runtime consumers read `env()` directly
 * (cache stack, queue stack, SMS SDKs). Saving them in the DB without propagating
 * to `.env` is a no-op at runtime — picking memcached in the admin UI but never
 * updating `.env` was the symptom this used to prevent.
 */
trait EnvSideEffects
{
	/**
	 * Mirror cache / memcached settings into `.env`. Fires per saved row;
	 * the env file is opened, mutated, and saved on each match. Save batches that
	 * touch multiple optimization fields will hit the file once per field — the
	 * cost is negligible and avoiding it would require the kind of per-request
	 * buffer this observer deliberately doesn't keep.
	 */
	private function writeOptimizationEnv(Setting $setting): void
	{
		$envKey = $this->resolveOptimizationEnvKey($setting->key);
		if ($envKey === null) return;
		// `wasChanged('value')` is FALSE for fresh INSERTs — Eloquent only populates
		// `$this->changes` from `performUpdate()`, never from `performInsert()`. The
		// first time an admin saves a setting that didn't previously have a row,
		// the row is INSERTED via `updateOrCreate(['key' => …], […])`, so without
		// the `wasRecentlyCreated` fallback the .env mirror would silently skip
		// the initial save and the runtime would keep reading the .env default.
		if (!$setting->wasChanged('value') && !$setting->wasRecentlyCreated) return;
		
		$this->writeEnvKey($envKey, $setting->value);
		
		// CACHE_PREFIX is paired with CACHE_STORE — re-asserted on every cache-driver
		// save to ensure cluster instances never collide.
		if ($setting->key === 'optimization_cache_driver') {
			DotenvEditor::setKey('CACHE_PREFIX', 'lc_');
		}
		
		DotenvEditor::save();
	}
	
	/**
	 * Map the flat optimization key to its corresponding env var, or return null
	 * if the key isn't one we mirror. Memcached server slots are dynamic
	 * (`optimization_memcached_servers_<N>_host` / `_port`) so they get their own
	 * regex match rather than living in the static map.
	 */
	private function resolveOptimizationEnvKey(string $key): ?string
	{
		$staticMap = [
			'optimization_cache_driver'            => 'CACHE_STORE',
			'optimization_memcached_persistent_id' => 'MEMCACHED_PERSISTENT_ID',
			'optimization_memcached_sasl_username' => 'MEMCACHED_USERNAME',
			'optimization_memcached_sasl_password' => 'MEMCACHED_PASSWORD',
		];
		
		if (array_key_exists($key, $staticMap)) {
			return $staticMap[$key];
		}
		
		if (preg_match('/^optimization_memcached_servers_(\d+)_host$/', $key, $m)) {
			return 'MEMCACHED_SERVER_' . $m[1] . '_HOST';
		}
		if (preg_match('/^optimization_memcached_servers_(\d+)_port$/', $key, $m)) {
			return 'MEMCACHED_SERVER_' . $m[1] . '_PORT';
		}
		
		return null;
	}
	
	/**
	 * Mirror the queue driver into `.env`. Single key so we keep this concise
	 * rather than spawning a separate ConfigTrait member for one mapping.
	 */
	private function writeQueueEnv(Setting $setting): void
	{
		if ($setting->key !== 'queue_driver') return;
		// See `writeOptimizationEnv()` for why `wasRecentlyCreated` is checked too:
		// Eloquent does not populate `$this->changes` on INSERT, so first-time
		// saves would otherwise short-circuit and leave QUEUE_CONNECTION pointing
		// at the .env default (typically `database`) regardless of the admin pick.
		if (!$setting->wasChanged('value') && !$setting->wasRecentlyCreated) return;
		
		$this->writeEnvKey('QUEUE_CONNECTION', $setting->value);
		DotenvEditor::save();
	}
	
	/**
	 * Mirror SMS provider credentials into `.env`. Twilio + Vonage SDKs read their
	 * auth env vars at boot; the DB copy is just so the admin form can show what's
	 * configured.
	 */
	private function writeSmsEnv(Setting $setting): void
	{
		$map = [
			'sms_vonage_key'             => 'VONAGE_KEY',
			'sms_vonage_secret'          => 'VONAGE_SECRET',
			'sms_vonage_application_id'  => 'VONAGE_APPLICATION_ID',
			'sms_vonage_from'            => 'VONAGE_SMS_FROM',
			'sms_twilio_username'        => 'TWILIO_USERNAME',
			'sms_twilio_password'        => 'TWILIO_PASSWORD',
			'sms_twilio_auth_token'      => 'TWILIO_AUTH_TOKEN',
			'sms_twilio_account_sid'     => 'TWILIO_ACCOUNT_SID',
			'sms_twilio_from'            => 'TWILIO_FROM',
			'sms_twilio_alpha_sender'    => 'TWILIO_ALPHA_SENDER',
			'sms_twilio_sms_service_sid' => 'TWILIO_SMS_SERVICE_SID',
			'sms_twilio_debug_to'        => 'TWILIO_DEBUG_TO',
		];
		
		if (!array_key_exists($setting->key, $map)) return;
		// See `writeOptimizationEnv()` for the `wasRecentlyCreated` rationale —
		// without it, the first save of a Twilio / Vonage credential row would
		// never reach the SDK env vars (the SDKs read them at boot, so the DB
		// copy alone would be a runtime no-op).
		if (!$setting->wasChanged('value') && !$setting->wasRecentlyCreated) return;
		
		$this->writeEnvKey($map[$setting->key], $setting->value);
		DotenvEditor::save();
	}
	
	/**
	 * Set when non-empty, delete when blanked. Mirrors the legacy SmsTrait pattern
	 * so clearing a credential in the admin form removes it from `.env` rather
	 * than leaving a stale value.
	 */
	private function writeEnvKey(string $envKey, mixed $value): void
	{
		$value = is_scalar($value) ? (string)$value : '';
		if ($value !== '') {
			DotenvEditor::setKey($envKey, $value);
		} else {
			DotenvEditor::deleteKey($envKey);
		}
	}
}
