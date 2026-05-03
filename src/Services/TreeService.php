<?php

namespace App\Services;

/**
 * Pure-PHP implementation of the dot-prop path helpers used by the Node.js
 * TreeService.  Supports bracket array notation: customers[0].name
 *
 * No package required — ~80 lines of recursive array manipulation.
 */
class TreeService
{
    /**
     * Get a value at a dot-path (bracket notation supported).
     * Returns the entire document when $path is null / empty.
     */
    public function getPath(array $doc, ?string $path): mixed
    {
        if ($path === null || $path === '') {
            return $doc;
        }
        $keys = $this->parsePath($path);
        return $this->getValue($doc, $keys);
    }

    /**
     * Set a value at a dot-path.
     * Returns the updated document.
     */
    public function setPath(array $doc, string $path, mixed $value): array
    {
        $keys = $this->parsePath($path);
        return $this->setValue($doc, $keys, $value);
    }

    /**
     * Replace the entire document (must be an object / assoc array).
     */
    public function replaceDocument(array $value): array
    {
        return $value;
    }

    /**
     * Deep-merge $patch into $doc (RFC 7396 JSON Merge Patch semantics).
     */
    public function patchMerge(array $doc, array $patch): array
    {
        return $this->deepMerge($doc, $patch);
    }

    /**
     * Delete the key at the given dot-path.
     * Returns the updated document.
     */
    public function deletePath(array $doc, string $path): array
    {
        $keys = $this->parsePath($path);
        return $this->deleteValue($doc, $keys);
    }

    // ── internal helpers ───────────────────────────────────────────────────

    /** Convert "a.b[0].c" → ["a", "b", 0, "c"] */
    private function parsePath(string $path): array
    {
        // Replace [n] with .n
        $normalised = preg_replace('/\[(\d+)\]/', '.$1', $path);
        $parts = explode('.', ltrim($normalised, '.'));
        return array_map(fn($p) => is_numeric($p) ? (int) $p : $p, $parts);
    }

    private function getValue(mixed $node, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (is_array($node) && array_key_exists($key, $node)) {
                $node = $node[$key];
            } else {
                return null;
            }
        }
        return $node;
    }

    private function setValue(array $doc, array $keys, mixed $value): array
    {
        if (empty($keys)) {
            return is_array($value) ? $value : $doc;
        }
        $key  = array_shift($keys);
        $node = $doc[$key] ?? (is_int($keys[0] ?? null) ? [] : []);
        $doc[$key] = empty($keys) ? $value : $this->setValue(is_array($node) ? $node : [], $keys, $value);
        return $doc;
    }

    private function deleteValue(array $doc, array $keys): array
    {
        if (empty($keys)) {
            return $doc;
        }
        $key = array_shift($keys);
        if (!array_key_exists($key, $doc)) {
            return $doc;
        }
        if (empty($keys)) {
            unset($doc[$key]);
        } else {
            $doc[$key] = $this->deleteValue(is_array($doc[$key]) ? $doc[$key] : [], $keys);
        }
        return $doc;
    }

    private function deepMerge(array $base, array $patch): array
    {
        foreach ($patch as $k => $v) {
            if (is_array($v) && isset($base[$k]) && is_array($base[$k]) && !array_is_list($v)) {
                $base[$k] = $this->deepMerge($base[$k], $v);
            } else {
                $base[$k] = $v;
            }
        }
        return $base;
    }
}
