<?php
/**
 * Optional Supabase safe-mode configuration helpers.
 * This does not replace the primary MySQL/PDO app database.
 */

function supabaseUrl(): string {
    return getenv('SUPABASE_URL') ?: (defined('SUPABASE_URL') ? trim((string) SUPABASE_URL) : '');
}

function supabaseAnonKey(): string {
    return getenv('SUPABASE_ANON_KEY') ?: (defined('SUPABASE_ANON_KEY') ? trim((string) SUPABASE_ANON_KEY) : '');
}

function isSupabaseConfigured(): bool {
    return supabaseUrl() !== '' && supabaseAnonKey() !== '';
}
