<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Store\Common\Filters;

enum FilterComparisonOperator: string {
	case EQ = '=';
	case GT = '>';
	case LT = '<';
	case GTE = '>=';
	case LTE = '<=';
	case NEQ = '!=';
	case IN = 'IN';
	case NIN = 'NOT IN';
	case LIKE = 'LIKE';
	case NLIKE = 'NOT LIKE';
}
