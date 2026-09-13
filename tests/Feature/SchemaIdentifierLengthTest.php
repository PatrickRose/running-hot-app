<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * That every name the schema gives an index or a foreign key is one MySQL will
 * accept.
 *
 * MySQL rejects an identifier longer than 64 characters. SQLite has no such
 * limit, so a migration that trips it passes every test and every local
 * `migrate:fresh`, and then fails on a deployed database - halfway through, and
 * with the table already created, since MySQL cannot roll back DDL. Three of
 * these reached production before this test existed.
 *
 * Laravel derives an index name from the table and every column in it
 * (`Blueprint::createIndexName`), so the names that overflow are the composite
 * ones on tables whose own name is already long. The remedy is to pass an
 * explicit name as the second argument.
 */
class SchemaIdentifierLengthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * MySQL's limit, from which this whole test follows.
     */
    private const MAX_LENGTH = 64;

    /**
     * The three that had to be named by hand, and what they were named.
     *
     * Asserted by name because an explicit name is the fix: were one dropped,
     * the derived name would come back and the deployment would break again.
     */
    private const NAMED_BY_HAND = [
        'facility_protection_cards_facility_card_unique',
        'protection_card_holdings_corporation_card_unique',
        'council_ballot_allocations_ballot_resolution_unique',
    ];

    public function test_every_index_name_fits_within_mysqls_limit(): void
    {
        foreach ($this->indexNames() as $table => $names) {
            foreach ($names as $name) {
                $this->assertLessThanOrEqual(
                    self::MAX_LENGTH,
                    strlen($name),
                    sprintf(
                        'The index %s on %s is %d characters, and MySQL allows %d. '
                        .'Pass a shorter name as the second argument to the index.',
                        $name,
                        $table,
                        strlen($name),
                        self::MAX_LENGTH,
                    ),
                );
            }
        }
    }

    /**
     * The name Laravel derives for a foreign key is table, column and
     * "_foreign", and it is held to the same limit.
     *
     * SQLite does not name its foreign key constraints, so unlike the indexes
     * above these cannot be read back off the built schema - the name is
     * derived here the way Blueprint::createIndexName derives it.
     */
    public function test_every_foreign_key_name_fits_within_mysqls_limit(): void
    {
        $checked = 0;

        foreach ($this->tables() as $table) {
            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                $name = strtolower($table.'_'.implode('_', $foreignKey['columns']).'_foreign');
                $checked++;

                $this->assertLessThanOrEqual(
                    self::MAX_LENGTH,
                    strlen($name),
                    sprintf('The foreign key %s would be %d characters, and MySQL allows %d.', $name, strlen($name), self::MAX_LENGTH),
                );
            }
        }

        $this->assertGreaterThan(20, $checked, 'Far too few foreign keys were found for this to have read the real schema.');
    }

    /**
     * The checks above are only worth having if they are reading real names.
     *
     * A reader that returned nothing, or that missed the generated names and
     * saw only SQLite's own, would pass them while proving nothing - so the
     * count has to be of the order of the schema, and the three names that were
     * chosen by hand have to be among what it found.
     */
    public function test_the_reader_finds_the_names_the_migrations_actually_create(): void
    {
        $names = collect($this->indexNames())->flatten()->all();

        $this->assertGreaterThan(
            30,
            count($names),
            'Far too few indexes were found for this to have read the real schema.',
        );

        foreach (self::NAMED_BY_HAND as $name) {
            $this->assertContains(
                $name,
                $names,
                sprintf('%s is missing. An index named by hand to fit MySQL has lost its name.', $name),
            );
        }
    }

    /**
     * Every index the migrations create, keyed by the table it sits on.
     *
     * SQLite's own automatic indexes are dropped: they are named for it rather
     * than by Laravel, so they are neither at risk nor within our gift.
     *
     * @return array<string, array<int, string>>
     */
    private function indexNames(): array
    {
        $indexes = [];

        foreach ($this->tables() as $table) {
            foreach (Schema::getIndexes($table) as $index) {
                $name = $index['name'] ?? null;

                if ($name === null || $name === '' || str_starts_with($name, 'sqlite_')) {
                    continue;
                }

                $indexes[$table][] = $name;
            }
        }

        return $indexes;
    }

    /**
     * Unqualified, which matters: the listing is schema-qualified by default,
     * so a table comes back as "main.games" on SQLite and carries the whole
     * database name on MySQL. Deriving a foreign key name from that measured
     * the prefix as well and called a 46 character name 66.
     *
     * @return array<int, string>
     */
    private function tables(): array
    {
        return array_values(array_filter(
            Schema::getTableListing(schemaQualified: false),
            fn (string $table): bool => ! str_starts_with($table, 'sqlite_'),
        ));
    }
}
