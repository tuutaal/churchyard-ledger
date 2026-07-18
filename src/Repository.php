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

    public function saveCemeteryCoordinates(array $data): bool
    {
        if ($this->db === null) {
            return false;
        }

        $latitude = trim((string) ($data['latitude'] ?? ''));
        $longitude = trim((string) ($data['longitude'] ?? ''));
        if ($latitude === '' && $longitude === '') {
            $values = ['latitude' => null, 'longitude' => null, 'id' => $this->cemeteryId()];
        } elseif ($latitude === '' || $longitude === '' || !is_numeric($latitude) || !is_numeric($longitude)) {
            return false;
        } else {
            $lat = (float) $latitude;
            $lon = (float) $longitude;
            if ($lat < -85 || $lat > 85 || $lon < -180 || $lon > 180) {
                return false;
            }
            $values = [
                'latitude' => number_format($lat, 7, '.', ''),
                'longitude' => number_format($lon, 7, '.', ''),
                'id' => $this->cemeteryId(),
            ];
        }

        try {
            $this->db->prepare('update cemeteries set latitude = :latitude, longitude = :longitude where id = :id')->execute($values);
            $this->audit('update', 'Cemetery', (string) $values['id'], 'Updated cemetery GPS coordinates');
            return true;
        } catch (Throwable) {
            return false;
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

    public function people(string $query = ''): array
    {
        if ($this->db === null) {
            return SampleData::people();
        }

        try {
            $sql = 'select id, legal_name, birth_date_text, death_date_text, visibility, confidence from people';
            $params = [];
            if ($query !== '') {
                $sql .= ' where legal_name like :query_legal_name
                    or given_name like :query_given_name
                    or family_name like :query_family_name
                    or maiden_name like :query_maiden_name
                    or birth_date_text like :query_birth_date
                    or death_date_text like :query_death_date
                    or notes like :query_notes';
                $like = '%' . $this->escapeLike($query) . '%';
                $params = [
                    'query_legal_name' => $like,
                    'query_given_name' => $like,
                    'query_family_name' => $like,
                    'query_maiden_name' => $like,
                    'query_birth_date' => $like,
                    'query_death_date' => $like,
                    'query_notes' => $like,
                ];
            }
            $sql .= ' order by legal_name';
            $statement = $this->db->prepare($sql);
            $statement->execute($params);
            $rows = $statement->fetchAll();
            return $rows ?: ($query === '' ? SampleData::people() : []);
        } catch (Throwable) {
            return $query === '' ? SampleData::people() : [];
        }
    }

    public function person(string $id): ?array
    {
        if ($this->db === null) {
            return null;
        }

        try {
            $statement = $this->db->prepare('select * from people where id = :id limit 1');
            $statement->execute(['id' => $id]);
            $person = $statement->fetch() ?: null;
            if ($person && !empty($person['alternate_names'])) {
                $names = json_decode((string) $person['alternate_names'], true);
                $person['alternate_names_text'] = is_array($names) ? implode(', ', $names) : '';
            }
            return $person;
        } catch (Throwable) {
            return null;
        }
    }

    public function savePerson(?string $id, array $data): ?string
    {
        if ($this->db === null || trim((string) ($data['legal_name'] ?? '')) === '') {
            return null;
        }

        $id ??= self::id();
        $alternateNames = array_values(array_filter(array_map('trim', explode(',', (string) ($data['alternate_names_text'] ?? '')))));
        $values = [
            'id' => $id,
            'organization_id' => $this->organizationId(),
            'legal_name' => trim((string) $data['legal_name']),
            'given_name' => $this->blankToNull($data['given_name'] ?? null),
            'family_name' => $this->blankToNull($data['family_name'] ?? null),
            'maiden_name' => $this->blankToNull($data['maiden_name'] ?? null),
            'birth_date_text' => $this->blankToNull($data['birth_date_text'] ?? null),
            'death_date_text' => $this->blankToNull($data['death_date_text'] ?? null),
            'alternate_names' => $alternateNames ? json_encode($alternateNames) : null,
            'notes' => $this->blankToNull($data['notes'] ?? null),
            'visibility' => $this->allowed($data['visibility'] ?? '', ['private', 'public'], 'private'),
            'confidence' => $this->allowed($data['confidence'] ?? '', ['confirmed', 'probable', 'conflicting', 'unknown'], 'unknown'),
        ];

        try {
            if ($this->person($id)) {
                $sql = 'update people set legal_name = :legal_name, given_name = :given_name, family_name = :family_name,
                    maiden_name = :maiden_name, birth_date_text = :birth_date_text, death_date_text = :death_date_text,
                    alternate_names = :alternate_names, notes = :notes, visibility = :visibility, confidence = :confidence
                    where id = :id';
                $this->db->prepare($sql)->execute(array_diff_key($values, ['organization_id' => true]));
                $this->audit('update', 'Person', $id, 'Updated person record for ' . $values['legal_name']);
            } else {
                $sql = 'insert into people (id, organization_id, legal_name, given_name, family_name, maiden_name, birth_date_text,
                    death_date_text, alternate_names, notes, visibility, confidence)
                    values (:id, :organization_id, :legal_name, :given_name, :family_name, :maiden_name, :birth_date_text,
                    :death_date_text, :alternate_names, :notes, :visibility, :confidence)';
                $this->db->prepare($sql)->execute($values);
                $this->audit('create', 'Person', $id, 'Created person record for ' . $values['legal_name']);
            }
            $this->saveCustomFieldValues('person', $id, $data);
            return $id;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Create or update a person while preserving admin-curated fields (visibility,
     * confidence, notes, alternate names) unless explicitly present in $data. Used by
     * CLI importers so a re-run never clobbers manual edits made through the UI.
     */
    public function upsertPerson(?string $id, array $data): ?string
    {
        if ($id !== null) {
            $existing = $this->person($id);
            if ($existing !== null) {
                $data += [
                    'visibility' => $existing['visibility'],
                    'confidence' => $existing['confidence'],
                    'notes' => $existing['notes'],
                    'alternate_names_text' => $existing['alternate_names_text'] ?? null,
                ];
            }
        }

        return $this->savePerson($id, $data);
    }

    /**
     * Create or update an interment while preserving admin-curated fields (visibility,
     * confidence, notes, disposition, burial permit, plot position) unless explicitly
     * present in $data. Used by CLI importers so a re-run never clobbers manual edits.
     */
    public function upsertInterment(?string $id, array $data, array $files = [], string $root = ''): ?string
    {
        if ($id !== null) {
            $existing = $this->interment($id);
            if ($existing !== null) {
                $data += [
                    'visibility' => $existing['visibility'],
                    'confidence' => $existing['confidence'],
                    'notes' => $existing['notes'],
                    'disposition_type' => $existing['disposition_type'],
                    'burial_permit_number' => $existing['burial_permit_number'],
                    'plot_position' => $existing['plot_position'],
                ];
            }
        }

        return $this->saveInterment($id, $data, $files, $root);
    }

    public function plots(string $query = ''): array
    {
        if ($this->db === null) {
            return SampleData::plots();
        }

        try {
            $where = '';
            $params = [];
            if ($query !== '') {
                $where = ' where plots.identifier like :query_identifier
                    or sections.code like :query_section_code
                    or sections.name like :query_section_name
                    or plots.row_label like :query_row
                    or plots.lot like :query_lot
                    or plots.status like :query_status
                    or plots.notes like :query_notes';
                $like = '%' . $this->escapeLike($query) . '%';
                $params = [
                    'query_identifier' => $like,
                    'query_section_code' => $like,
                    'query_section_name' => $like,
                    'query_row' => $like,
                    'query_lot' => $like,
                    'query_status' => $like,
                    'query_notes' => $like,
                ];
            }
            $statement = $this->db->prepare(
                'select plots.id, plots.identifier, sections.code as section_code, plots.row_label, plots.lot, plots.status, plots.visibility, plots.confidence
                 from plots
                 left join sections on sections.id = plots.section_id' . $where . '
                 order by plots.identifier'
            );
            $statement->execute($params);
            $rows = $statement->fetchAll();
            return $rows ?: ($query === '' ? SampleData::plots() : []);
        } catch (Throwable) {
            return $query === '' ? SampleData::plots() : [];
        }
    }

    public function mapPlots(): array
    {
        if ($this->db === null) {
            return array_map(fn (array $plot) => $plot + [
                'section_name' => $plot['section_code'] ? 'Section ' . $plot['section_code'] : 'Unsectioned',
                'interment_count' => 0,
                'interment_names' => '',
            ], SampleData::plots());
        }

        try {
            return $this->db->query(
                'select plots.id, plots.identifier, plots.row_label, plots.lot, plots.status, plots.visibility, plots.confidence, plots.geometry,
                    sections.code as section_code, sections.name as section_name, sections.sort_order as section_sort_order,
                    count(interments.id) as interment_count,
                    group_concat(people.legal_name order by people.legal_name separator ", ") as interment_names
                 from plots
                 left join sections on sections.id = plots.section_id
                 left join interments on interments.plot_id = plots.id
                 left join people on people.id = interments.person_id
                 group by plots.id, plots.identifier, plots.row_label, plots.lot, plots.status, plots.visibility, plots.confidence, plots.geometry,
                    sections.code, sections.name, sections.sort_order
                 order by coalesce(sections.sort_order, 9999), sections.code, plots.identifier'
            )->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Individual grave marker points captured from the hand-drawn cemetery
     * block map, grouped by plot. See scripts/apply_cemetery_map_geometry.php.
     */
    public function mapMarkers(): array
    {
        if ($this->db === null) {
            return [];
        }

        try {
            $rows = $this->db->query(
                'select interments.id as interment_id, interments.plot_id, interments.map_point,
                    people.legal_name
                 from interments
                 join people on people.id = interments.person_id
                 where interments.map_point is not null and interments.plot_id is not null'
            )->fetchAll();
        } catch (Throwable) {
            return [];
        }

        $byPlot = [];
        foreach ($rows as $row) {
            $point = json_decode((string) $row['map_point'], true);
            if (!is_array($point) || !isset($point['x'], $point['y'])) {
                continue;
            }
            $byPlot[$row['plot_id']][] = [
                'id' => (string) $row['interment_id'],
                'name' => (string) $row['legal_name'],
                'x' => (int) $point['x'],
                'y' => (int) $point['y'],
            ];
        }

        return $byPlot;
    }

    public function plot(string $id): ?array
    {
        if ($this->db === null) {
            return null;
        }

        try {
            $statement = $this->db->prepare('select * from plots where id = :id limit 1');
            $statement->execute(['id' => $id]);
            return $statement->fetch() ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    public function sections(): array
    {
        if ($this->db === null) {
            return [];
        }

        try {
            return $this->db->query('select id, code, name from sections order by sort_order, code')->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    public function mapLayer(): ?array
    {
        if ($this->db === null) {
            return null;
        }

        try {
            $statement = $this->db->prepare(
                "select id, name, layer_type, source_url, source_metadata, visibility, confidence
                 from map_layers
                 where cemetery_id = :cemetery_id and layer_type = 'uploaded_image'
                 order by sort_order, created_at desc
                 limit 1"
            );
            $statement->execute(['cemetery_id' => $this->cemeteryId()]);
            return $statement->fetch() ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    public function saveMapLayer(array $data, array $files = [], string $root = ''): bool
    {
        if ($this->db === null) {
            return false;
        }

        $sourceUrl = $this->blankToNull($data['source_url'] ?? null);
        $uploadedUrl = $this->storeMapUpload($files, $root);
        if ($uploadedUrl !== null) {
            $sourceUrl = $uploadedUrl;
        }

        if ($sourceUrl === null) {
            return false;
        }

        $name = trim((string) ($data['name'] ?? 'Cemetery map'));
        if ($name === '') {
            $name = 'Cemetery map';
        }

        try {
            $existing = $this->mapLayer();
            $values = [
                'id' => $existing['id'] ?? self::id(),
                'cemetery_id' => $this->cemeteryId(),
                'name' => $name,
                'source_url' => $sourceUrl,
                'visibility' => $this->allowed($data['visibility'] ?? '', ['private', 'public'], 'private'),
                'confidence' => $this->allowed($data['confidence'] ?? '', ['confirmed', 'probable', 'conflicting', 'unknown'], 'unknown'),
            ];

            if ($existing) {
                $this->db->prepare(
                    'update map_layers
                     set name = :name, source_url = :source_url, visibility = :visibility, confidence = :confidence
                     where id = :id'
                )->execute(array_diff_key($values, ['cemetery_id' => true]));
                $this->audit('update', 'Map Layer', (string) $values['id'], 'Updated cemetery map background');
            } else {
                $this->db->prepare(
                    "insert into map_layers (id, cemetery_id, name, layer_type, source_url, visibility, confidence)
                     values (:id, :cemetery_id, :name, 'uploaded_image', :source_url, :visibility, :confidence)"
                )->execute($values);
                $this->audit('create', 'Map Layer', (string) $values['id'], 'Added cemetery map background');
            }

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function savePlotGeometry(string $plotId, string $geometryJson): bool
    {
        if ($this->db === null || $plotId === '') {
            return false;
        }

        $geometry = $this->normalizePlotGeometry($geometryJson);
        if ($geometry === null) {
            return false;
        }

        try {
            $statement = $this->db->prepare('update plots set geometry = :geometry where id = :id');
            $statement->execute([
                'id' => $plotId,
                'geometry' => $geometry,
            ]);
            $this->audit('update', 'Plot', $plotId, 'Updated plot map boundary');
            return $this->plot($plotId) !== null;
        } catch (Throwable) {
            return false;
        }
    }

    public function saveIntermentMapPoint(string $intermentId, int $x, int $y): bool
    {
        if ($this->db === null || $intermentId === '') {
            return false;
        }

        try {
            $statement = $this->db->prepare('update interments set map_point = :map_point where id = :id');
            $statement->execute([
                'id' => $intermentId,
                'map_point' => json_encode(['x' => $x, 'y' => $y]),
            ]);
            return $statement->rowCount() > 0 || $this->interment($intermentId) !== null;
        } catch (Throwable) {
            return false;
        }
    }

    public function createPlotFromGeometry(array $data, string $geometryJson): ?string
    {
        if ($this->db === null || trim((string) ($data['new_identifier'] ?? '')) === '') {
            return null;
        }

        $geometry = $this->normalizePlotGeometry($geometryJson);
        if ($geometry === null) {
            return null;
        }

        $id = self::id();
        $values = [
            'id' => $id,
            'cemetery_id' => $this->cemeteryId(),
            'section_id' => $this->blankToNull($data['new_section_id'] ?? null),
            'identifier' => trim((string) $data['new_identifier']),
            'row_label' => $this->blankToNull($data['new_row_label'] ?? null),
            'lot' => $this->blankToNull($data['new_lot'] ?? null),
            'status' => $this->allowed($data['new_status'] ?? '', ['available', 'reserved', 'occupied', 'sold', 'unknown', 'unusable', 'needs_verification'], 'unknown'),
            'notes' => $this->blankToNull($data['new_notes'] ?? null),
            'visibility' => $this->allowed($data['new_visibility'] ?? '', ['private', 'public'], 'private'),
            'confidence' => $this->allowed($data['new_confidence'] ?? '', ['confirmed', 'probable', 'conflicting', 'unknown'], 'unknown'),
            'geometry' => $geometry,
        ];

        try {
            $sql = 'insert into plots (id, cemetery_id, section_id, identifier, row_label, lot, status, notes, visibility, confidence, geometry)
                values (:id, :cemetery_id, :section_id, :identifier, :row_label, :lot, :status, :notes, :visibility, :confidence, :geometry)';
            $this->db->prepare($sql)->execute($values);
            $this->audit('create', 'Plot', $id, 'Created plot ' . $values['identifier'] . ' from map boundary');

            return $id;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Find-or-create a plot by identifier. On update, admin-curated fields (status,
     * visibility, confidence, notes) are preserved unless explicitly present in $data,
     * so re-running an importer never clobbers manual edits made through the UI.
     */
    public function upsertPlot(array $data): ?string
    {
        if ($this->db === null) {
            return null;
        }

        $identifier = trim((string) ($data['identifier'] ?? ''));
        if ($identifier === '') {
            return null;
        }

        $existingId = $this->plotIdByIdentifier($identifier);
        if ($existingId !== null) {
            $existing = $this->plot($existingId);
            if ($existing !== null) {
                $data += [
                    'status' => $existing['status'],
                    'visibility' => $existing['visibility'],
                    'confidence' => $existing['confidence'],
                    'notes' => $existing['notes'],
                    'section_id' => $existing['section_id'],
                ];
            }
        }

        return $this->savePlot($existingId, $data);
    }

    public function upsertSection(string $code, string $name = ''): ?string
    {
        if ($this->db === null) {
            return null;
        }

        $code = trim($code);
        if ($code === '') {
            return null;
        }

        $existing = $this->sectionIdByCode($code);
        if ($existing !== null) {
            return $existing;
        }

        try {
            $id = self::id();
            $this->db->prepare(
                'insert into sections (id, cemetery_id, code, name, sort_order, visibility, confidence)
                 values (:id, :cemetery_id, :code, :name, :sort_order, :visibility, :confidence)'
            )->execute([
                'id' => $id,
                'cemetery_id' => $this->cemeteryId(),
                'code' => $code,
                'name' => $name !== '' ? $name : $code,
                'sort_order' => 0,
                'visibility' => 'private',
                'confidence' => 'unknown',
            ]);
            $this->audit('create', 'Section', $id, 'Created section ' . $code);
            return $id;
        } catch (Throwable) {
            return null;
        }
    }

    public function savePlot(?string $id, array $data): ?string
    {
        if ($this->db === null || trim((string) ($data['identifier'] ?? '')) === '') {
            return null;
        }

        $id ??= self::id();
        $values = [
            'id' => $id,
            'cemetery_id' => $this->cemeteryId(),
            'section_id' => $this->blankToNull($data['section_id'] ?? null),
            'identifier' => trim((string) $data['identifier']),
            'row_label' => $this->blankToNull($data['row_label'] ?? null),
            'lot' => $this->blankToNull($data['lot'] ?? null),
            'status' => $this->allowed($data['status'] ?? '', ['available', 'reserved', 'occupied', 'sold', 'unknown', 'unusable', 'needs_verification'], 'unknown'),
            'notes' => $this->blankToNull($data['notes'] ?? null),
            'visibility' => $this->allowed($data['visibility'] ?? '', ['private', 'public'], 'private'),
            'confidence' => $this->allowed($data['confidence'] ?? '', ['confirmed', 'probable', 'conflicting', 'unknown'], 'unknown'),
        ];

        try {
            if ($this->plot($id)) {
                $sql = 'update plots set section_id = :section_id, identifier = :identifier, row_label = :row_label,
                    lot = :lot, status = :status, notes = :notes, visibility = :visibility, confidence = :confidence
                    where id = :id';
                $this->db->prepare($sql)->execute(array_diff_key($values, ['cemetery_id' => true]));
                $this->audit('update', 'Plot', $id, 'Updated plot ' . $values['identifier']);
            } else {
                $sql = 'insert into plots (id, cemetery_id, section_id, identifier, row_label, lot, status, notes, visibility, confidence)
                    values (:id, :cemetery_id, :section_id, :identifier, :row_label, :lot, :status, :notes, :visibility, :confidence)';
                $this->db->prepare($sql)->execute($values);
                $this->audit('create', 'Plot', $id, 'Created plot ' . $values['identifier']);
            }
            $this->saveCustomFieldValues('plot', $id, $data);
            return $id;
        } catch (Throwable) {
            return null;
        }
    }

    public function interments(string $query = ''): array
    {
        if ($this->db === null) {
            return [];
        }

        try {
            $where = '';
            $params = [];
            if ($query !== '') {
                $where = ' where people.legal_name like :query_name
                    or plots.identifier like :query_plot
                    or interments.disposition_type like :query_disposition
                    or interments.interment_date_text like :query_interment_date
                    or interments.burial_permit_number like :query_permit
                    or interments.marker_transcription like :query_marker
                    or interments.notes like :query_notes';
                $like = '%' . $this->escapeLike($query) . '%';
                $params = [
                    'query_name' => $like,
                    'query_plot' => $like,
                    'query_disposition' => $like,
                    'query_interment_date' => $like,
                    'query_permit' => $like,
                    'query_marker' => $like,
                    'query_notes' => $like,
                ];
            }

            $statement = $this->db->prepare(
                'select interments.id, interments.disposition_type, interments.interment_date_text, interments.visibility,
                    interments.confidence, people.legal_name as person_name, plots.identifier as plot_identifier
                 from interments
                 left join people on people.id = interments.person_id
                 left join plots on plots.id = interments.plot_id' . $where . '
                 order by plots.identifier, people.legal_name'
            );
            $statement->execute($params);
            return $statement->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    public function interment(string $id): ?array
    {
        if ($this->db === null) {
            return null;
        }

        try {
            $statement = $this->db->prepare('select * from interments where id = :id limit 1');
            $statement->execute(['id' => $id]);
            return $statement->fetch() ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    public function personOptions(): array
    {
        if ($this->db === null) {
            return [];
        }

        try {
            return $this->db->query('select id, legal_name from people order by legal_name')->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    public function plotOptions(): array
    {
        if ($this->db === null) {
            return [];
        }

        try {
            return $this->db->query('select id, identifier from plots order by identifier')->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    public function users(): array
    {
        if ($this->db === null) {
            return [];
        }

        try {
            return $this->db->query(
                'select users.id, users.email, users.name, users.is_system_admin, organization_members.role
                 from users
                 left join organization_members on organization_members.user_id = users.id
                 order by users.email'
            )->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    public function saveUser(array $data): bool
    {
        if ($this->db === null || !filter_var((string) ($data['email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $email = strtolower(trim((string) $data['email']));
        $name = $this->blankToNull($data['name'] ?? null);
        $role = $this->allowed($data['role'] ?? '', ['admin', 'editor', 'viewer'], 'editor');
        $password = trim((string) ($data['password'] ?? ''));

        try {
            $statement = $this->db->prepare('select id from users where email = :email limit 1');
            $statement->execute(['email' => $email]);
            $userId = $statement->fetchColumn();
            $userId = $userId === false ? self::id() : (string) $userId;

            if ($this->userExists($userId)) {
                $values = ['id' => $userId, 'email' => $email, 'name' => $name];
                $passwordSql = '';
                if ($password !== '') {
                    $passwordSql = ', hashed_password = :hashed_password';
                    $values['hashed_password'] = password_hash($password, PASSWORD_DEFAULT);
                }
                $this->db->prepare('update users set email = :email, name = :name' . $passwordSql . ' where id = :id')->execute($values);
            } else {
                $this->db->prepare(
                    'insert into users (id, email, name, hashed_password)
                     values (:id, :email, :name, :hashed_password)'
                )->execute([
                    'id' => $userId,
                    'email' => $email,
                    'name' => $name,
                    'hashed_password' => $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null,
                ]);
            }

            $this->db->prepare(
                'insert into organization_members (id, organization_id, user_id, role)
                 values (:id, :organization_id, :user_id, :role)
                 on duplicate key update role = values(role)'
            )->execute([
                'id' => self::id(),
                'organization_id' => $this->organizationId(),
                'user_id' => $userId,
                'role' => $role,
            ]);

            $this->audit('setup', 'User', $userId, 'Saved user ' . $email . ' as ' . $role);
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function saveInterment(?string $id, array $data, array $files = [], string $root = ''): ?string
    {
        if (
            $this->db === null
            || trim((string) ($data['person_id'] ?? '')) === ''
        ) {
            return null;
        }

        $id ??= self::id();
        $values = [
            'id' => $id,
            'cemetery_id' => $this->cemeteryId(),
            'plot_id' => $this->blankToNull($data['plot_id'] ?? null),
            'person_id' => trim((string) $data['person_id']),
            'disposition_type' => $this->allowed($data['disposition_type'] ?? '', ['unknown', 'casket', 'cremains', 'other'], 'unknown'),
            'interment_date_text' => $this->blankToNull($data['interment_date_text'] ?? null),
            'burial_permit_number' => $this->blankToNull($data['burial_permit_number'] ?? null),
            'marker_transcription' => $this->blankToNull($data['marker_transcription'] ?? null),
            'plot_position' => $this->blankToNull($data['plot_position'] ?? null),
            'notes' => $this->blankToNull($data['notes'] ?? null),
            'visibility' => $this->allowed($data['visibility'] ?? '', ['private', 'public'], 'private'),
            'confidence' => $this->allowed($data['confidence'] ?? '', ['confirmed', 'probable', 'conflicting', 'unknown'], 'unknown'),
        ];

        try {
            if ($this->interment($id)) {
                $sql = 'update interments set plot_id = :plot_id, person_id = :person_id, disposition_type = :disposition_type,
                    interment_date_text = :interment_date_text, burial_permit_number = :burial_permit_number,
                    marker_transcription = :marker_transcription, plot_position = :plot_position, notes = :notes,
                    visibility = :visibility, confidence = :confidence where id = :id';
                $this->db->prepare($sql)->execute(array_diff_key($values, ['cemetery_id' => true]));
                $this->audit('update', 'Interment', $id, 'Updated interment record');
            } else {
                $sql = 'insert into interments (id, cemetery_id, plot_id, person_id, disposition_type, interment_date_text,
                    burial_permit_number, marker_transcription, plot_position, notes, visibility, confidence)
                    values (:id, :cemetery_id, :plot_id, :person_id, :disposition_type, :interment_date_text,
                    :burial_permit_number, :marker_transcription, :plot_position, :notes, :visibility, :confidence)';
                $this->db->prepare($sql)->execute($values);
                $this->audit('create', 'Interment', $id, 'Created interment record');
            }

            $this->attachPhoto($id, $values, $data);
            $this->attachUploadedPhoto($id, $values, $files, $root);
            $this->saveCustomFieldValues('interment', $id, $data);
            return $id;
        } catch (Throwable) {
            return null;
        }
    }

    public function mediaForInterment(string $intermentId): array
    {
        if ($this->db === null) {
            return [];
        }

        try {
            $statement = $this->db->prepare('select title, url from media where interment_id = :interment_id and media_type = :media_type order by created_at desc');
            $statement->execute(['interment_id' => $intermentId, 'media_type' => 'image']);
            return $statement->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    public function customFieldDefinitions(?string $entityType = null): array
    {
        if ($this->db === null) {
            return [];
        }

        try {
            $sql = 'select id, entity_type, field_key, label, field_type, help_text, sort_order, is_required
                from custom_field_definitions
                where organization_id = :organization_id';
            $params = ['organization_id' => $this->organizationId()];
            if ($entityType !== null) {
                $sql .= ' and entity_type = :entity_type';
                $params['entity_type'] = $this->allowedEntityType($entityType);
            }
            $sql .= ' order by entity_type, sort_order, label';
            $statement = $this->db->prepare($sql);
            $statement->execute($params);
            return $statement->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    public function saveCustomFieldDefinition(array $data): bool
    {
        if ($this->db === null || trim((string) ($data['label'] ?? '')) === '') {
            return false;
        }

        $entityType = $this->allowedEntityType((string) ($data['entity_type'] ?? 'plot'));
        $label = trim((string) $data['label']);
        $fieldKey = $this->customFieldKey((string) ($data['field_key'] ?? ''), $label);
        $fieldType = $this->allowed((string) ($data['field_type'] ?? ''), ['text', 'textarea', 'date', 'number', 'url'], 'text');

        try {
            $this->db->prepare('insert into custom_field_definitions
                (id, organization_id, entity_type, field_key, label, field_type, help_text, sort_order, is_required)
                values (:id, :organization_id, :entity_type, :field_key, :label, :field_type, :help_text, :sort_order, :is_required)')->execute([
                    'id' => self::id(),
                    'organization_id' => $this->organizationId(),
                    'entity_type' => $entityType,
                    'field_key' => $fieldKey,
                    'label' => $label,
                    'field_type' => $fieldType,
                    'help_text' => $this->blankToNull($data['help_text'] ?? null),
                    'sort_order' => (int) ($data['sort_order'] ?? 0),
                    'is_required' => isset($data['is_required']) ? 1 : 0,
                ]);
            $this->audit('create', 'Custom Field', $fieldKey, 'Created custom ' . $entityType . ' field ' . $label);
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function customFieldValues(string $entityType, string $entityId): array
    {
        if ($this->db === null || $entityId === '') {
            return [];
        }

        try {
            $statement = $this->db->prepare(
                'select custom_field_definitions.field_key, custom_field_values.value_text
                 from custom_field_values
                 join custom_field_definitions on custom_field_definitions.id = custom_field_values.field_definition_id
                 where custom_field_values.entity_type = :entity_type and custom_field_values.entity_id = :entity_id'
            );
            $statement->execute(['entity_type' => $this->allowedEntityType($entityType), 'entity_id' => $entityId]);
            $values = [];
            foreach ($statement->fetchAll() as $row) {
                $values[$row['field_key']] = $row['value_text'];
            }
            return $values;
        } catch (Throwable) {
            return [];
        }
    }

    public function publicInterments(string $query = ''): array
    {
        if ($this->db === null) {
            return [];
        }

        try {
            $where = "interments.visibility = 'public'
                    and people.visibility = 'public'
                    and (plots.id is null or plots.visibility = 'public')";
            $params = [];

            if ($query !== '') {
                $where .= " and (
                    people.legal_name like :query_legal_name
                    or people.given_name like :query_given_name
                    or people.family_name like :query_family_name
                    or people.maiden_name like :query_maiden_name
                    or people.birth_date_text like :query_birth_date
                    or people.death_date_text like :query_death_date
                    or interments.interment_date_text like :query_interment_date
                    or interments.marker_transcription like :query_marker
                )";
                $like = '%' . $this->escapeLike($query) . '%';
                $params = [
                    'query_legal_name' => $like,
                    'query_given_name' => $like,
                    'query_family_name' => $like,
                    'query_maiden_name' => $like,
                    'query_birth_date' => $like,
                    'query_death_date' => $like,
                    'query_interment_date' => $like,
                    'query_marker' => $like,
                ];
            }

            $statement = $this->db->prepare(
                "select interments.id, people.legal_name as person_name,
                    people.birth_date_text, people.death_date_text, plots.identifier as plot_identifier,
                    (
                        select media.url from media
                        where media.interment_id = interments.id and media.visibility = 'public'
                        order by media.created_at desc limit 1
                    ) as photo_url
                 from interments
                 join people on people.id = interments.person_id
                 left join plots on plots.id = interments.plot_id
                 where {$where}
                 order by people.family_name, people.legal_name, plots.identifier"
            );
            $statement->execute($params);
            return $statement->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    public function export(string $type): ?array
    {
        if ($this->db === null) {
            return null;
        }

        $exports = [
            'people' => [
                'headers' => ['id', 'legal_name', 'given_name', 'family_name', 'maiden_name', 'alternate_names', 'birth_date_text', 'death_date_text', 'visibility', 'confidence', 'notes'],
                'sql' => 'select id, legal_name, given_name, family_name, maiden_name, alternate_names, birth_date_text, death_date_text, visibility, confidence, notes from people order by legal_name',
            ],
            'plots' => [
                'headers' => ['id', 'identifier', 'section_code', 'row_label', 'lot', 'status', 'visibility', 'confidence', 'notes', 'geometry'],
                'sql' => 'select plots.id, plots.identifier, sections.code as section_code, plots.row_label, plots.lot, plots.status, plots.visibility, plots.confidence, plots.notes, plots.geometry from plots left join sections on sections.id = plots.section_id order by plots.identifier',
            ],
            'interments' => [
                'headers' => ['id', 'person_name', 'plot_identifier', 'disposition_type', 'interment_date_text', 'burial_permit_number', 'plot_position', 'marker_transcription', 'visibility', 'confidence', 'notes'],
                'sql' => 'select interments.id, people.legal_name as person_name, plots.identifier as plot_identifier, interments.disposition_type, interments.interment_date_text, interments.burial_permit_number, interments.plot_position, interments.marker_transcription, interments.visibility, interments.confidence, interments.notes from interments left join people on people.id = interments.person_id left join plots on plots.id = interments.plot_id order by plots.identifier, people.legal_name',
            ],
            'media' => [
                'headers' => ['id', 'title', 'media_type', 'url', 'storage_key', 'cemetery_id', 'plot_id', 'person_id', 'interment_id', 'owner_id', 'visibility', 'confidence'],
                'sql' => 'select id, title, media_type, url, storage_key, cemetery_id, plot_id, person_id, interment_id, owner_id, visibility, confidence from media order by created_at desc',
            ],
        ];

        if (!isset($exports[$type])) {
            return null;
        }

        try {
            return [
                'headers' => $exports[$type]['headers'],
                'rows' => $this->db->query($exports[$type]['sql'])->fetchAll(),
            ];
        } catch (Throwable) {
            return ['headers' => $exports[$type]['headers'], 'rows' => []];
        }
    }

    public function importCsv(string $type, string $path): array
    {
        $result = [
            'message' => '',
            'processed' => 0,
            'imported' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        if ($this->db === null) {
            $result['message'] = 'Connect the database before importing CSV records.';
            return $result;
        }

        if (!in_array($type, ['people', 'plots', 'interments'], true)) {
            $result['message'] = 'Choose people, plots, or interments before importing.';
            return $result;
        }

        if ($path === '' || !is_readable($path)) {
            $result['message'] = 'The uploaded CSV file could not be read.';
            return $result;
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            $result['message'] = 'The uploaded CSV file could not be opened.';
            return $result;
        }

        $headers = fgetcsv($handle);
        if (!is_array($headers)) {
            fclose($handle);
            $result['message'] = 'The CSV file needs a header row.';
            return $result;
        }

        $headers = array_map(fn (string $header) => $this->normalizeCsvHeader($header), $headers);
        if (!$this->hasRequiredImportHeaders($type, $headers)) {
            fclose($handle);
            $result['message'] = $this->requiredImportHeaderMessage($type);
            return $result;
        }

        while (($values = fgetcsv($handle)) !== false) {
            if ($this->csvRowIsBlank($values)) {
                continue;
            }

            $result['processed']++;
            if ($result['processed'] > 1000) {
                $result['skipped']++;
                $this->addImportError($result, 'Only the first 1,000 data rows were imported. Split larger files into smaller batches.');
                break;
            }

            $row = $this->combineCsvRow($headers, $values);
            $error = match ($type) {
                'people' => $this->importPersonRow($row),
                'plots' => $this->importPlotRow($row),
                'interments' => $this->importIntermentRow($row),
                default => 'Unsupported import type.',
            };

            if ($error === null) {
                $result['imported']++;
            } else {
                $result['skipped']++;
                $this->addImportError($result, 'Row ' . ($result['processed'] + 1) . ': ' . $error);
            }
        }

        fclose($handle);

        $result['message'] = sprintf(
            'Import complete: %d imported, %d skipped, %d processed.',
            $result['imported'],
            $result['skipped'],
            $result['processed']
        );

        if ($result['imported'] > 0) {
            $this->audit('import', pretty($type), '', 'Imported ' . $result['imported'] . ' ' . pretty($type) . ' rows from CSV');
        }

        return $result;
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

    private function organizationId(): string
    {
        return (string) $this->db?->query('select id from organizations order by created_at limit 1')->fetchColumn();
    }

    private function cemeteryId(): string
    {
        return (string) $this->db?->query('select id from cemeteries order by created_at limit 1')->fetchColumn();
    }

    private function audit(string $action, string $entityType, string $entityId, string $summary): void
    {
        if ($this->db === null) {
            return;
        }

        try {
            $this->db->prepare('insert into audit_logs (id, organization_id, action, entity_type, entity_id, summary)
                values (:id, :organization_id, :action, :entity_type, :entity_id, :summary)')->execute([
                    'id' => self::id(),
                    'organization_id' => $this->organizationId(),
                    'action' => $action,
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'summary' => $summary,
                ]);
        } catch (Throwable) {
        }
    }

    private function saveCustomFieldValues(string $entityType, string $entityId, array $data): void
    {
        if ($this->db === null || $entityId === '') {
            return;
        }

        $entityType = $this->allowedEntityType($entityType);
        foreach ($this->customFieldDefinitions($entityType) as $definition) {
            $key = (string) $definition['field_key'];
            $value = $this->blankToNull($data['custom'][$key] ?? $data['custom_' . $key] ?? null);
            if ($value === null) {
                continue;
            }

            try {
                $this->db->prepare('insert into custom_field_values
                    (id, field_definition_id, entity_type, entity_id, value_text)
                    values (:id, :field_definition_id, :entity_type, :entity_id, :value_text)
                    on duplicate key update value_text = values(value_text)')->execute([
                        'id' => self::id(),
                        'field_definition_id' => $definition['id'],
                        'entity_type' => $entityType,
                        'entity_id' => $entityId,
                        'value_text' => $value,
                    ]);
            } catch (Throwable) {
            }
        }
    }

    private function saveCustomFieldValuesFromImport(string $entityType, string $entityId, array $row): void
    {
        $custom = [];
        foreach ($this->customFieldDefinitions($entityType) as $definition) {
            $key = (string) $definition['field_key'];
            $labelKey = $this->normalizeCsvHeader((string) $definition['label']);
            if (isset($row[$key])) {
                $custom[$key] = $row[$key];
            } elseif (isset($row[$labelKey])) {
                $custom[$key] = $row[$labelKey];
            }
        }

        if ($custom) {
            $this->saveCustomFieldValues($entityType, $entityId, ['custom' => $custom]);
        }
    }

    private function importPersonRow(array $row): ?string
    {
        if (trim((string) ($row['legal_name'] ?? '')) === '') {
            return 'legal_name is required.';
        }

        if (empty($row['alternate_names_text']) && !empty($row['alternate_names'])) {
            $alternateNames = json_decode((string) $row['alternate_names'], true);
            $row['alternate_names_text'] = is_array($alternateNames)
                ? implode(', ', array_filter(array_map('strval', $alternateNames)))
                : (string) $row['alternate_names'];
        }

        $id = $this->blankToNull($row['id'] ?? null);
        $savedId = $this->savePerson($id, $row);
        if ($savedId === null) {
            return 'Could not save this person.';
        }

        $this->saveCustomFieldValuesFromImport('person', $savedId, $row);
        return null;
    }

    private function importPlotRow(array $row): ?string
    {
        if (trim((string) ($row['identifier'] ?? '')) === '') {
            return 'identifier is required.';
        }

        $sectionCode = $this->blankToNull($row['section_code'] ?? null);
        if ($sectionCode !== null) {
            $sectionId = $this->sectionIdByCode($sectionCode);
            if ($sectionId === null) {
                return 'section_code "' . $sectionCode . '" was not found.';
            }
            $row['section_id'] = $sectionId;
        }

        $id = $this->blankToNull($row['id'] ?? null) ?? $this->plotIdByIdentifier((string) $row['identifier']);
        $savedId = $this->savePlot($id, $row);
        if ($savedId === null) {
            return 'Could not save this plot.';
        }

        $this->saveCustomFieldValuesFromImport('plot', $savedId, $row);
        return null;
    }

    private function importIntermentRow(array $row): ?string
    {
        $personId = $this->blankToNull($row['person_id'] ?? null);
        if ($personId === null && $this->blankToNull($row['person_name'] ?? null) !== null) {
            $personId = $this->personIdByName((string) $row['person_name']);
        }

        if ($personId === null || $this->person($personId) === null) {
            return 'person_id or person_name must match an existing person.';
        }

        $plotId = $this->blankToNull($row['plot_id'] ?? null);
        if ($plotId === null && $this->blankToNull($row['plot_identifier'] ?? null) !== null) {
            $plotId = $this->plotIdByIdentifier((string) $row['plot_identifier']);
            if ($plotId === null) {
                return 'plot_identifier "' . $row['plot_identifier'] . '" was not found.';
            }
        }

        if ($plotId !== null && $this->plot($plotId) === null) {
            return 'plot_id or plot_identifier must match an existing plot.';
        }

        $row['person_id'] = $personId;
        $row['plot_id'] = $plotId;
        $id = $this->blankToNull($row['id'] ?? null);

        $savedId = $this->saveInterment($id, $row);
        if ($savedId === null) {
            return 'Could not save this interment.';
        }

        $this->saveCustomFieldValuesFromImport('interment', $savedId, $row);
        return null;
    }

    private function sectionIdByCode(string $code): ?string
    {
        if ($this->db === null) {
            return null;
        }

        try {
            $statement = $this->db->prepare('select id from sections where code = :code order by sort_order, code limit 1');
            $statement->execute(['code' => trim($code)]);
            $id = $statement->fetchColumn();
            return $id === false ? null : (string) $id;
        } catch (Throwable) {
            return null;
        }
    }

    private function personIdByName(string $name): ?string
    {
        if ($this->db === null) {
            return null;
        }

        try {
            $statement = $this->db->prepare('select id from people where legal_name = :name order by created_at limit 1');
            $statement->execute(['name' => trim($name)]);
            $id = $statement->fetchColumn();
            return $id === false ? null : (string) $id;
        } catch (Throwable) {
            return null;
        }
    }

    private function plotIdByIdentifier(string $identifier): ?string
    {
        if ($this->db === null) {
            return null;
        }

        try {
            $statement = $this->db->prepare('select id from plots where cemetery_id = :cemetery_id and identifier = :identifier limit 1');
            $statement->execute(['cemetery_id' => $this->cemeteryId(), 'identifier' => trim($identifier)]);
            $id = $statement->fetchColumn();
            return $id === false ? null : (string) $id;
        } catch (Throwable) {
            return null;
        }
    }

    private function hasRequiredImportHeaders(string $type, array $headers): bool
    {
        if ($type === 'people') {
            return in_array('legal_name', $headers, true);
        }

        if ($type === 'plots') {
            return in_array('identifier', $headers, true);
        }

        return (in_array('person_id', $headers, true) || in_array('person_name', $headers, true))
            && (in_array('plot_id', $headers, true) || in_array('plot_identifier', $headers, true));
    }

    private function requiredImportHeaderMessage(string $type): string
    {
        return match ($type) {
            'people' => 'People imports need a legal_name header.',
            'plots' => 'Plot imports need an identifier header.',
            'interments' => 'Interment imports need person_id or person_name, plus plot_id or plot_identifier.',
            default => 'The CSV file is missing required headers.',
        };
    }

    private function normalizeCsvHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
        $header = strtolower(trim($header));
        $header = str_replace([' ', '-'], '_', $header);

        return match ($header) {
            'name' => 'legal_name',
            'alternate_names_text' => 'alternate_names',
            'plot' => 'plot_identifier',
            'person' => 'person_name',
            'row' => 'row_label',
            default => $header,
        };
    }

    private function combineCsvRow(array $headers, array $values): array
    {
        $row = [];
        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }

            $row[$header] = trim((string) ($values[$index] ?? ''));
        }

        return $row;
    }

    private function csvRowIsBlank(array $values): bool
    {
        foreach ($values as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function addImportError(array &$result, string $error): void
    {
        if (count($result['errors']) < 20) {
            $result['errors'][] = $error;
        }
    }

    private function attachPhoto(string $intermentId, array $interment, array $data): void
    {
        if ($this->db === null) {
            return;
        }

        $url = $this->blankToNull($data['photo_url'] ?? null);
        if ($url === null) {
            return;
        }

        try {
            $existing = $this->db->prepare('select count(*) from media where interment_id = :interment_id and url = :url');
            $existing->execute(['interment_id' => $intermentId, 'url' => $url]);
            if ((int) $existing->fetchColumn() > 0) {
                return;
            }

            $this->db->prepare('insert into media (id, organization_id, cemetery_id, plot_id, person_id, interment_id, title,
                media_type, url, visibility, confidence)
                values (:id, :organization_id, :cemetery_id, :plot_id, :person_id, :interment_id, :title,
                :media_type, :url, :visibility, :confidence)')->execute([
                    'id' => self::id(),
                    'organization_id' => $this->organizationId(),
                    'cemetery_id' => $interment['cemetery_id'],
                    'plot_id' => $interment['plot_id'],
                    'person_id' => $interment['person_id'],
                    'interment_id' => $intermentId,
                    'title' => 'Grave photo',
                    'media_type' => 'image',
                    'url' => $url,
                    'visibility' => $interment['visibility'],
                    'confidence' => $interment['confidence'],
                ]);
            $this->audit('create', 'Media', $intermentId, 'Attached grave photo URL');
        } catch (Throwable) {
        }
    }

    private function attachUploadedPhoto(string $intermentId, array $interment, array $files, string $root): void
    {
        if ($root === '' || empty($files['photo_upload']) || !is_array($files['photo_upload'])) {
            return;
        }

        $file = $files['photo_upload'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return;
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || (int) ($file['size'] ?? 0) > 8 * 1024 * 1024) {
            return;
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return;
        }

        $this->attachPhotoFromPath($intermentId, $interment, $tmpName, (string) ($file['name'] ?? ''), $root, 'Uploaded grave photo');
    }

    /**
     * Stores a grave photo from a plain filesystem path (not a PHP upload) under
     * uploads/grave-photos/, matching the convention used by the web upload form.
     * Used by the web upload path (via attachUploadedPhoto) and by CLI importers.
     */
    public function attachPhotoFromPath(string $intermentId, array $interment, string $sourcePath, string $originalName, string $root, string $title = 'Grave photo'): bool
    {
        if ($this->db === null || $root === '' || $sourcePath === '' || !is_file($sourcePath)) {
            return false;
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $extensions = ['jpg' => 'jpg', 'jpeg' => 'jpg', 'png' => 'png', 'gif' => 'gif', 'webp' => 'webp'];
        if (!isset($extensions[$extension]) || @getimagesize($sourcePath) === false) {
            return false;
        }

        $directory = rtrim($root, '/\\') . '/uploads/grave-photos';
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            return false;
        }

        $fileName = self::id() . '.' . $extensions[$extension];
        $target = $directory . '/' . $fileName;
        if (!copy($sourcePath, $target)) {
            return false;
        }

        $url = '/uploads/grave-photos/' . $fileName;

        try {
            $this->db->prepare('insert into media (id, organization_id, cemetery_id, plot_id, person_id, interment_id, title,
                media_type, storage_key, url, visibility, confidence)
                values (:id, :organization_id, :cemetery_id, :plot_id, :person_id, :interment_id, :title,
                :media_type, :storage_key, :url, :visibility, :confidence)')->execute([
                    'id' => self::id(),
                    'organization_id' => $this->organizationId(),
                    'cemetery_id' => $interment['cemetery_id'],
                    'plot_id' => $interment['plot_id'],
                    'person_id' => $interment['person_id'],
                    'interment_id' => $intermentId,
                    'title' => $title,
                    'media_type' => 'image',
                    'storage_key' => 'uploads/grave-photos/' . $fileName,
                    'url' => $url,
                    'visibility' => $interment['visibility'],
                    'confidence' => $interment['confidence'],
                ]);
            $this->audit('create', 'Media', $intermentId, 'Uploaded grave photo');
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function storeMapUpload(array $files, string $root): ?string
    {
        if ($root === '' || empty($files['map_image']) || !is_array($files['map_image'])) {
            return null;
        }

        $file = $files['map_image'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || (int) ($file['size'] ?? 0) > 16 * 1024 * 1024) {
            return null;
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return null;
        }

        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $extensions = ['jpg' => 'jpg', 'jpeg' => 'jpg', 'png' => 'png', 'gif' => 'gif', 'webp' => 'webp'];
        if (!isset($extensions[$extension]) || @getimagesize($tmpName) === false) {
            return null;
        }

        $directory = rtrim($root, '/\\') . '/uploads/maps';
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            return null;
        }

        $fileName = self::id() . '.' . $extensions[$extension];
        if (!move_uploaded_file($tmpName, $directory . '/' . $fileName)) {
            return null;
        }

        return '/uploads/maps/' . $fileName;
    }

    private function userExists(string $id): bool
    {
        if ($this->db === null) {
            return false;
        }

        $statement = $this->db->prepare('select count(*) from users where id = :id');
        $statement->execute(['id' => $id]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function blankToNull(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function allowed(mixed $value, array $allowed, string $fallback): string
    {
        $value = (string) $value;
        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    private function allowedEntityType(string $entityType): string
    {
        return $this->allowed($entityType, ['person', 'plot', 'interment'], 'plot');
    }

    private function normalizePlotGeometry(string $geometryJson): ?string
    {
        $geometry = json_decode($geometryJson, true);
        if (!is_array($geometry)) {
            return null;
        }

        $points = $geometry['points'] ?? [];
        if (!is_array($points) || count($points) < 3 || count($points) > 30) {
            return null;
        }

        $normalized = [];
        foreach ($points as $point) {
            if (!is_array($point) || count($point) < 2 || !is_numeric($point[0]) || !is_numeric($point[1])) {
                return null;
            }

            $x = max(0, min(10000, (int) round((float) $point[0])));
            $y = max(0, min(10000, (int) round((float) $point[1])));
            $normalized[] = [$x, $y];
        }

        return json_encode(['type' => 'Polygon', 'points' => $normalized]);
    }

    private function customFieldKey(string $fieldKey, string $label): string
    {
        $key = strtolower(trim($fieldKey !== '' ? $fieldKey : $label));
        $key = preg_replace('/[^a-z0-9]+/', '_', $key) ?? $key;
        $key = trim($key, '_');

        return $key !== '' ? substr($key, 0, 120) : 'custom_field';
    }

    private function escapeLike(string $value): string
    {
        return strtr($value, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']);
    }

    private static function id(): string
    {
        $hex = bin2hex(random_bytes(16));
        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
    }
}
