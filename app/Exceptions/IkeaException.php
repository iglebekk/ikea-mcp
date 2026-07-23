<?php

namespace App\Exceptions;

use Exception;

/**
 * Domain exception for all IKEA upstream failures, carrying a machine-readable
 * reason so MCP tools can return consistent, actionable error messages.
 */
class IkeaException extends Exception
{
    public const NOT_FOUND = 'not_found';

    public const NOT_IN_MARKET = 'not_in_market';

    public const MARKET_UNSUPPORTED = 'market_unsupported';

    public const LANGUAGE_UNSUPPORTED = 'language_unsupported';

    public const INVALID_ITEM_NO = 'invalid_item_no';

    public const TEMPORARY = 'temporary';

    public const RATE_LIMITED = 'rate_limited';

    public const BLOCKED = 'blocked';

    public const SCHEMA_CHANGED = 'schema_changed';

    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public function isTemporary(): bool
    {
        return in_array($this->reason, [self::TEMPORARY, self::RATE_LIMITED], true);
    }
}
