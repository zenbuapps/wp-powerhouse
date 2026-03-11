<?php

declare(strict_types=1);

namespace J7\Powerhouse\Shared\Enums;

enum EContentType: string {
	case HTML       = 'html';
	case PLAIN_TEXT = 'text';

	case JSON     = 'json';
	case XML      = 'xml';
	case MARKDOWN = 'md';
}
