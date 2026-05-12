<?php
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Anesti;

final class SampleData
{
    public static function cemetery(): array
    {
        return [
            'name' => 'Grace Rural Cemetery',
            'description' => 'A small church cemetery maintained by volunteers.',
            'public_site_enabled' => 1,
            'default_visibility' => 'private',
        ];
    }

    public static function people(): array
    {
        return [
            ['id' => 'demo-john', 'legal_name' => 'John Thomas Miller', 'birth_date_text' => 'March 4, 1881', 'death_date_text' => 'June 12, 1946', 'visibility' => 'public', 'confidence' => 'confirmed'],
            ['id' => 'demo-sarah', 'legal_name' => 'Sarah Ann Miller', 'birth_date_text' => '1885', 'death_date_text' => '1952', 'visibility' => 'public', 'confidence' => 'confirmed'],
            ['id' => 'demo-mary', 'legal_name' => 'Mary E. Holloway', 'birth_date_text' => '', 'death_date_text' => 'possibly 1918', 'visibility' => 'public', 'confidence' => 'probable'],
        ];
    }

    public static function plots(): array
    {
        return [
            ['id' => 'demo-a01', 'identifier' => 'A-01', 'section_code' => 'A', 'row_label' => '1', 'lot' => '1', 'status' => 'occupied', 'visibility' => 'public', 'confidence' => 'confirmed'],
            ['id' => 'demo-a02', 'identifier' => 'A-02', 'section_code' => 'A', 'row_label' => '1', 'lot' => '2', 'status' => 'occupied', 'visibility' => 'public', 'confidence' => 'confirmed'],
            ['id' => 'demo-a03', 'identifier' => 'A-03', 'section_code' => 'A', 'row_label' => '1', 'lot' => '3', 'status' => 'needs_verification', 'visibility' => 'public', 'confidence' => 'conflicting'],
            ['id' => 'demo-a04', 'identifier' => 'A-04', 'section_code' => 'A', 'row_label' => '1', 'lot' => '4', 'status' => 'reserved', 'visibility' => 'private', 'confidence' => 'confirmed'],
            ['id' => 'demo-b01', 'identifier' => 'B-01', 'section_code' => 'B', 'row_label' => '1', 'lot' => '1', 'status' => 'available', 'visibility' => 'private', 'confidence' => 'confirmed'],
            ['id' => 'demo-b02', 'identifier' => 'B-02', 'section_code' => 'B', 'row_label' => '1', 'lot' => '2', 'status' => 'sold', 'visibility' => 'private', 'confidence' => 'confirmed'],
            ['id' => 'demo-b03', 'identifier' => 'B-03', 'section_code' => 'B', 'row_label' => '1', 'lot' => '3', 'status' => 'unusable', 'visibility' => 'private', 'confidence' => 'probable'],
        ];
    }
}
