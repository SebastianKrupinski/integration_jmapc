/**
 * SPDX-FileCopyrightText: Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createAppConfig } from '@nextcloud/vite-config'
import { join } from 'path'

// replaced by vite
declare const __dirname: string

export default createAppConfig({
	UserSettings: join(__dirname, 'src', 'UserSettings.ts'),
	AdminSettings: join(__dirname, 'src', 'AdminSettings.ts'),
}, {
	inlineCSS: { relativeCSSInjection: true },
	thirdPartyLicense: false,
})
