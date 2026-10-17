<?php

/**
 * Wallos validate() stores user text with one layer of HTML entities. Decode
 * that storage layer before escaping it for the output context.
 */
function dialStoredPlainText($value): string
{
    return htmlspecialchars_decode((string) ($value ?? ''), ENT_QUOTES);
}

function dialStoredHtml($value): string
{
    return htmlspecialchars(dialStoredPlainText($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
