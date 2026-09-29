<?php
/** Shared admin helpers: slugging, and creating taxonomy rows inline. */

declare(strict_types=1);

function admin_slugify(string $s): string
{
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    $s = trim($s, '-');
    return $s !== '' ? $s : 'product';
}

function admin_unique_slug(string $slug, int $ignoreId): string
{
    $base = $slug;
    $n = 2;
    while (true) {
        $row = db_one('SELECT id FROM products WHERE slug = ?' . ($ignoreId > 0 ? ' AND id <> ?' : ''), $ignoreId > 0 ? [$slug, $ignoreId] : [$slug]);
        if ($row === null) {
            return $slug;
        }
        $slug = $base . '-' . $n++;
    }
}

/** Return an unused slug within a table/column, optionally ignoring a row. */
function admin_unique_slug_in(string $table, string $column, string $slug, int $ignoreId = 0): string
{
    $base = $slug;
    $n = 2;
    while (true) {
        $row = db_one(
            "SELECT id FROM {$table} WHERE {$column} = ?" . ($ignoreId > 0 ? ' AND id <> ?' : ''),
            $ignoreId > 0 ? [$slug, $ignoreId] : [$slug]
        );
        if ($row === null) {
            return $slug;
        }
        $slug = $base . '-' . $n++;
    }
}

/**
 * Find a brand by name, creating it when it does not exist yet.
 *
 * @return array{id:int,name:string,slug:string,created:bool}
 */
function admin_find_or_create_brand(string $name): array
{
    $name = trim($name);
    $slug = admin_slugify($name);
    $row  = db_one('SELECT id, name, slug FROM brands WHERE name = ? OR slug = ? LIMIT 1', [$name, $slug]);
    if ($row !== null) {
        return ['id' => (int) $row['id'], 'name' => (string) $row['name'], 'slug' => (string) $row['slug'], 'created' => false];
    }
    $slug = admin_unique_slug_in('brands', 'slug', $slug);
    db_exec('INSERT INTO brands (slug, name) VALUES (?,?)', [$slug, $name]);
    return ['id' => (int) db_val('SELECT id FROM brands WHERE slug = ?', [$slug], 0), 'name' => $name, 'slug' => $slug, 'created' => true];
}

/**
 * Find a category by name, creating it under the given department when missing.
 *
 * @return array{id:int,name:string,slug:string,created:bool}
 */
function admin_find_or_create_category(string $name, string $department): array
{
    $name = trim($name);
    $slug = admin_slugify($name);
    $row  = db_one('SELECT id, name, slug FROM categories WHERE name = ? OR slug = ? LIMIT 1', [$name, $slug]);
    if ($row !== null) {
        return ['id' => (int) $row['id'], 'name' => (string) $row['name'], 'slug' => (string) $row['slug'], 'created' => false];
    }
    $slug = admin_unique_slug_in('categories', 'slug', $slug);
    db_exec(
        'INSERT INTO categories (slug, name, department, parent_id, is_active, sort_order) VALUES (?,?,?,NULL,1,0)',
        [$slug, $name, $department]
    );
    return ['id' => (int) db_val('SELECT id FROM categories WHERE slug = ?', [$slug], 0), 'name' => $name, 'slug' => $slug, 'created' => true];
}
