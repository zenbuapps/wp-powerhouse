<?php

declare(strict_types=1);

namespace J7\Powerhouse\Shared\Enums;

enum EOperater: string {

	case EXACT                     = 'exact';
	case EQUAL                     = 'eq';
	case NOT_EQUAL                 = 'ne';
	case EQUAL_SENSITIVE           = 'eqs';
	case NOT_EQUAL_SENSITIVE       = 'nes';
	case LESS                      = 'lt';
	case GREATER                   = 'gt';
	case LESS_OR_EQUAL             = 'lte';
	case GREATER_OR_EQUAL          = 'gte';
	case IN                        = 'in';
	case NOT_IN                    = 'nin';
	case IN_ARRAY                  = 'ina';
	case NOT_IN_ARRAY              = 'nina';
	case CONTAINS                  = 'contains';
	case NOT_CONTAINS              = 'ncontains';
	case CONTAINS_SENSITIVE        = 'containss';
	case NOT_CONTAINS_SENSITIVE    = 'ncontainss';
	case BETWEEN                   = 'between';
	case NOT_BETWEEN               = 'nbetween';
	case IS_NULL                   = 'null';
	case NOT_NULL                  = 'nnull';
	case STARTS_WITH               = 'startswith';
	case NOT_STARTS_WITH           = 'nstartswith';
	case STARTS_WITH_SENSITIVE     = 'startswiths';
	case NOT_STARTS_WITH_SENSITIVE = 'nstartswiths';
	case ENDS_WITH                 = 'endswith';
	case NOT_ENDS_WITH             = 'nendswith';
	case ENDS_WITH_SENSITIVE       = 'endswiths';
	case NOT_ENDS_WITH_SENSITIVE   = 'nendswiths';
	case OR                        = 'or';
	case AND                       = 'and';
}
