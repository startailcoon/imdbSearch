---
name: code-review
description: Repository-specific checks for imdbSearch — a client-side sql.js search UI plus a PHP TSV-to-SQLite importer. Use when reviewing changes to script.js, index.html, getDatabases.php, or databases/import.php.
---

# Code review guidelines for imdbSearch

This repo has no CI/test suite, so review is the only safety net before a
change reaches users. Flag anything below as a real bug, not a nitpick —
each one has caused an actual regression in this codebase before.

## Frontend (`script.js`, `index.html`)

- **SQL must be parameterized, never string-concatenated.** Any user input
  (`region`, `festival`, `attributes`, dates, etc.) that ends up in a query
  passed to `db.exec()` must go through bound `:name` parameters, not
  `"... '" + value + "'"`. A stray quote in concatenated SQL throws, and if
  the throw isn't caught it can leave the UI stuck (see below).
- **Every value written into generated HTML must be escaped** with
  `escapeHtml()` before use — in text content *and* in attribute values
  (e.g. `href='...'`, `value='...'`). This includes values that look
  "internal" (imported IDs, filenames from `getDatabases.php`): if it isn't
  a hardcoded literal, escape it.
- **CSV export fields must be quoted per RFC 4180.** A field containing a
  comma, double quote, `\r`, or `\n` must be wrapped in quotes with internal
  quotes doubled. Check `csvField()`/`createCSV()` cover all four
  characters, not just commas.
- **Data URIs must use `encodeURIComponent`, not `encodeURI`.** `encodeURI`
  leaves `#` (and a few other reserved characters) unescaped, which
  truncates a `data:` URI at that character.
- **Every `.then()` on a query/fetch promise needs a `.catch()`.** An
  uncaught rejection here previously left the "Querying database....."
  spinner and its `setInterval` running forever with no way to recover.
- **A control disabled at the start of an async flow must stay disabled
  until every part of that flow — including a `setTimeout`-deferred
  render — has actually finished,** not just until the awaited promise's
  synchronous portion returns. If a function schedules a delayed DOM
  update via `setTimeout`, make sure callers that need to know it's done
  actually `await` a promise that resolves inside that callback, not one
  that resolves immediately.
- **When replacing a cached resource (e.g. reopening a DB connection),
  construct/validate the replacement before tearing down the old one.** If
  building the new one can throw, tearing down the old one first leaves the
  cache pointing at a dead resource with no way to recover on retry.
- **Any new column added to the search form's query builder
  (`createQuery()`) should get a matching index in `import.php`** — see
  below.

## Importer (`databases/import.php`)

- **`fgets()` can return `false`** (empty/missing line). Never pass its
  result directly into a string function (`strlen()`, `str_replace()`,
  etc.) without checking for `false` first — PHP's implicit bool→string
  coercion can mask this until a stricter environment or an edge-case input
  (e.g. an empty file) trips it.
- **Byte-offset/progress tracking must stay internally consistent.** If a
  progress percentage is computed as `bytesRead / totalBytes`, make sure
  both sides account for the same bytes (e.g. don't measure `totalBytes`
  from the whole file while seeding `bytesRead` at 0 after the header is
  already consumed).
- **Every column the frontend filters or searches on needs a
  `CREATE INDEX IF NOT EXISTS` for it**, added after the bulk insert (not
  before — indexing before a large insert makes the import itself slow).
  Cross-check `script.js`'s `createQuery()` against the `CREATE INDEX`
  statements here; a filterable column with no index is a full-table-scan
  bug on an IMDB-scale table.

## When you can't run the app

There's no test suite to point to. Prefer suggesting a concrete manual
check (e.g. "start a local PHP server, load the page, and try a search
containing `'`/`"`/`#`/a comma") over assuming existing coverage catches a
regression — for this repo, it usually doesn't.
