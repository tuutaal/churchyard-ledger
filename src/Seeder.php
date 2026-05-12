<?php
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Anesti;

use PDO;

final class Seeder
{
    private const IDS = [
        'organization' => '00000000-0000-4000-8000-000000000001',
        'admin' => '00000000-0000-4000-8000-000000000002',
        'cemetery' => '00000000-0000-4000-8000-000000000003',
        'sectionA' => '00000000-0000-4000-8000-000000000004',
        'sectionB' => '00000000-0000-4000-8000-000000000005',
        'john' => '00000000-0000-4000-8000-000000000007',
        'sarah' => '00000000-0000-4000-8000-000000000008',
        'mary' => '00000000-0000-4000-8000-000000000009',
    ];

    public static function seed(PDO $db): void
    {
        self::insert($db, 'organizations', [
            'id' => self::IDS['organization'],
            'name' => 'Grace Rural Church',
            'slug' => 'grace-rural-church',
            'contact_email' => 'office@example.org',
            'public_site_enabled' => 1,
        ]);

        self::insert($db, 'users', [
            'id' => self::IDS['admin'],
            'email' => 'admin@example.org',
            'name' => 'Church Secretary',
        ]);

        self::insert($db, 'organization_members', [
            'id' => '00000000-0000-4000-8000-000000000017',
            'organization_id' => self::IDS['organization'],
            'user_id' => self::IDS['admin'],
            'role' => 'owner',
        ]);

        self::insert($db, 'cemeteries', [
            'id' => self::IDS['cemetery'],
            'organization_id' => self::IDS['organization'],
            'name' => 'Grace Rural Cemetery',
            'slug' => 'grace-rural-cemetery',
            'description' => 'A small church cemetery maintained by volunteers.',
            'city' => 'Fairview',
            'state' => 'IA',
            'public_site_enabled' => 1,
            'boundary_geojson' => json_encode(['type' => 'Polygon', 'coordinates' => []]),
        ]);

        self::insert($db, 'sections', [
            'id' => self::IDS['sectionA'],
            'cemetery_id' => self::IDS['cemetery'],
            'code' => 'A',
            'name' => 'Section A',
            'description' => 'Oldest section near the church lane.',
            'sort_order' => 1,
            'visibility' => 'public',
            'confidence' => 'confirmed',
        ]);

        self::insert($db, 'sections', [
            'id' => self::IDS['sectionB'],
            'cemetery_id' => self::IDS['cemetery'],
            'code' => 'B',
            'name' => 'Section B',
            'description' => 'Newer section with several records needing verification.',
            'sort_order' => 2,
            'visibility' => 'public',
            'confidence' => 'probable',
        ]);

        foreach (SampleData::plots() as $index => $plot) {
            self::insert($db, 'plots', [
                'id' => sprintf('00000000-0000-4000-8000-%012d', $index + 10),
                'cemetery_id' => self::IDS['cemetery'],
                'section_id' => $plot['section_code'] === 'A' ? self::IDS['sectionA'] : self::IDS['sectionB'],
                'identifier' => $plot['identifier'],
                'row_label' => $plot['row_label'],
                'lot' => $plot['lot'],
                'status' => $plot['status'],
                'geometry' => json_encode(['type' => 'Polygon', 'coordinates' => []]),
                'visibility' => $plot['visibility'],
                'confidence' => $plot['confidence'],
            ]);
        }

        foreach (SampleData::people() as $person) {
            $id = match ($person['legal_name']) {
                'John Thomas Miller' => self::IDS['john'],
                'Sarah Ann Miller' => self::IDS['sarah'],
                default => self::IDS['mary'],
            };

            self::insert($db, 'people', [
                'id' => $id,
                'organization_id' => self::IDS['organization'],
                'legal_name' => $person['legal_name'],
                'birth_date_text' => $person['birth_date_text'],
                'death_date_text' => $person['death_date_text'],
                'visibility' => $person['visibility'],
                'confidence' => $person['confidence'],
            ]);
        }

        self::insert($db, 'interments', [
            'id' => '00000000-0000-4000-8000-000000000018',
            'cemetery_id' => self::IDS['cemetery'],
            'plot_id' => '00000000-0000-4000-8000-000000000010',
            'person_id' => self::IDS['john'],
            'interment_date_text' => 'June 1946',
            'marker_transcription' => 'John T. Miller 1881-1946',
            'visibility' => 'public',
            'confidence' => 'confirmed',
        ]);

        self::insert($db, 'interments', [
            'id' => '00000000-0000-4000-8000-000000000019',
            'cemetery_id' => self::IDS['cemetery'],
            'plot_id' => '00000000-0000-4000-8000-000000000011',
            'person_id' => self::IDS['sarah'],
            'interment_date_text' => '1952',
            'marker_transcription' => 'Sarah A. Miller 1885-1952',
            'visibility' => 'public',
            'confidence' => 'confirmed',
        ]);

        self::insert($db, 'interments', [
            'id' => '00000000-0000-4000-8000-000000000020',
            'cemetery_id' => self::IDS['cemetery'],
            'plot_id' => '00000000-0000-4000-8000-000000000012',
            'person_id' => self::IDS['mary'],
            'marker_transcription' => 'Mary E. Holloway',
            'notes' => 'Plot location copied from handwritten trustee map.',
            'visibility' => 'public',
            'confidence' => 'probable',
        ]);

        self::insert($db, 'audit_logs', [
            'id' => '00000000-0000-4000-8000-000000000025',
            'organization_id' => self::IDS['organization'],
            'user_id' => self::IDS['admin'],
            'action' => 'setup',
            'entity_type' => 'Organization',
            'entity_id' => self::IDS['organization'],
            'summary' => 'Seeded sample church cemetery data.',
        ]);
    }

    private static function insert(PDO $db, string $table, array $data): void
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn (string $column) => ':' . $column, $columns);
        $updates = implode(', ', array_map(fn (string $column) => "$column = $column", $columns));

        $sql = sprintf(
            'insert into %s (%s) values (%s) on duplicate key update %s',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders),
            $updates
        );

        $db->prepare($sql)->execute($data);
    }
}
