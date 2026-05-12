<?php
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Anesti;

use PDO;
use Throwable;

final class Repository
{
    public function __construct(private readonly ?PDO $db)
    {
    }

    public function source(): string
    {
        return $this->db === null ? 'built-in sample data' : 'database-backed demo data';
    }

    public function cemetery(): array
    {
        if ($this->db === null) {
            return SampleData::cemetery();
        }

        try {
            $row = $this->db->query('select * from cemeteries order by created_at limit 1')->fetch();
            return $row ?: SampleData::cemetery();
        } catch (Throwable) {
            return SampleData::cemetery();
        }
    }

    public function counts(): array
    {
        if ($this->db === null) {
            $plots = SampleData::plots();
            $people = SampleData::people();
            return [
                'cemeteries' => 1,
                'plots' => count($plots),
                'interments' => 3,
                'public_records' => count(array_filter($plots, fn ($plot) => $plot['visibility'] === 'public')) + count($people),
            ];
        }

        try {
            return [
                'cemeteries' => (int) $this->db->query('select count(*) from cemeteries')->fetchColumn(),
                'plots' => (int) $this->db->query('select count(*) from plots')->fetchColumn(),
                'interments' => (int) $this->db->query('select count(*) from interments')->fetchColumn(),
                'public_records' => (int) $this->db->query("select count(*) from people where visibility = 'public'")->fetchColumn()
                    + (int) $this->db->query("select count(*) from plots where visibility = 'public'")->fetchColumn(),
            ];
        } catch (Throwable) {
            return ['cemeteries' => 1, 'plots' => 7, 'interments' => 3, 'public_records' => 6];
        }
    }

    public function people(): array
    {
        if ($this->db === null) {
            return SampleData::people();
        }

        try {
            $rows = $this->db->query('select id, legal_name, birth_date_text, death_date_text, visibility, confidence from people order by legal_name')->fetchAll();
            return $rows ?: SampleData::people();
        } catch (Throwable) {
            return SampleData::people();
        }
    }

    public function plots(): array
    {
        if ($this->db === null) {
            return SampleData::plots();
        }

        try {
            $rows = $this->db->query(
                'select plots.id, plots.identifier, sections.code as section_code, plots.row_label, plots.lot, plots.status, plots.visibility, plots.confidence
                 from plots
                 left join sections on sections.id = plots.section_id
                 order by plots.identifier'
            )->fetchAll();
            return $rows ?: SampleData::plots();
        } catch (Throwable) {
            return SampleData::plots();
        }
    }

    public function verificationItems(): array
    {
        $plots = array_filter($this->plots(), fn (array $plot) => $plot['confidence'] !== 'confirmed' || $plot['status'] === 'needs_verification');

        if (!$plots) {
            return [
                ['record' => 'Plot A-03', 'detail' => 'Conflicting owner notes', 'status' => 'Conflicting', 'tone' => 'danger'],
                ['record' => 'Mary E. Holloway', 'detail' => 'Probable death date from marker photo', 'status' => 'Probable', 'tone' => 'warning'],
            ];
        }

        return array_map(fn (array $plot) => [
            'record' => 'Plot ' . $plot['identifier'],
            'detail' => pretty($plot['status']) . ' with ' . pretty($plot['confidence']) . ' confidence',
            'status' => pretty($plot['confidence']),
            'tone' => $plot['confidence'] === 'conflicting' ? 'danger' : 'warning',
        ], array_slice(array_values($plots), 0, 4));
    }
}
