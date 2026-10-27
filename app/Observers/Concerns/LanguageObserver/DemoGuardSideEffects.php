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

namespace App\Observers\Concerns\LanguageObserver;

/**
 * Demo-website guard. Language create / update / delete are all blocked on
 * the demo domain so visitors poking the admin panel don't break the seed
 * fixture for everyone else.
 *
 * Returns the redirect-to-back response when triggered (matching the legacy
 * `back()` flow); callers use the return as a sentinel via `if ($r = $this->guardDemo())`
 * to short-circuit their hook.
 */
trait DemoGuardSideEffects
{
	private function blockOnDemoDomain(): mixed
	{
		if (!isDemoDomain()) {
			return null;
		}
		
		notification(trans('global.demo_mode_message'), 'error');
		
		return back();
	}
}
