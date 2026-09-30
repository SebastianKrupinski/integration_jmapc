/*
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export interface Collection {
	id: string | null
	ccid: string
	label: string
	enabled?: boolean
	color?: string
	hlockhb?: number
	count?: number
}
