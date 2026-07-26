<?php
/**
 * Regex patterns for SQL injection detection.
 *
 * These patterns are intentionally combination-based to reduce false positives
 * for normal names, course codes, academic sessions and search text.
 */

function sql_injection_patterns(): array {
    return [
        'tautology_comparison' => [
            'category' => 'tautology',
            'score' => 45,
            'regex' => '/(?:^|[\s\'"`()])(?:or|and)\s+(?:\d+\s*=\s*\d+|[\'"][^\'"]+[\'"]\s*=\s*[\'"][^\'"]+[\'"]|[a-z_][a-z0-9_]*\s*=\s*[a-z_][a-z0-9_]*)/i',
        ],
        'union_select' => [
            'category' => 'union',
            'score' => 75,
            'regex' => '/\bunion\s+(?:all\s+)?select\b/i',
        ],
        'sql_line_comment' => [
            'category' => 'comment',
            'score' => 25,
            'regex' => '/(?:^|[\s\'"`()])(?:--[^\r\n]*|#[^\r\n]*)/i',
        ],
        'sql_block_comment' => [
            'category' => 'comment',
            'score' => 30,
            'regex' => '/\/\*.*?\*\//s',
        ],
        'stacked_destructive_query' => [
            'category' => 'stacked_query',
            'score' => 80,
            'regex' => '/;\s*(?:drop|delete|update|insert|alter|truncate|create|replace)\b/i',
        ],
        'database_discovery' => [
            'category' => 'database_discovery',
            'score' => 65,
            'regex' => '/\b(?:information_schema|mysql\.user|show\s+(?:tables|databases|columns|variables|grants))\b/i',
        ],
        'time_based_payload' => [
            'category' => 'time_based',
            'score' => 75,
            'regex' => '/\b(?:sleep|benchmark|pg_sleep)\s*\(|\bwaitfor\s+delay\b/i',
        ],
        'file_or_command_payload' => [
            'category' => 'file_or_command',
            'score' => 80,
            'regex' => '/\b(?:load_file\s*\(|into\s+(?:out|dump)file\b|xp_cmdshell\b)/i',
        ],
    ];
}
