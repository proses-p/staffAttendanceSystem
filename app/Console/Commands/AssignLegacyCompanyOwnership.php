<?php

namespace App\Console\Commands;

use App\Services\LegacyCompanyOwnershipAssigner;
use App\Services\LocalDatabaseGuard;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use JsonException;

class AssignLegacyCompanyOwnership extends Command
{
    protected $signature = 'app:assign-legacy-company
        {mapping : Path to the verified JSON ownership mapping}
        {--apply : Persist assignments; otherwise perform a validation-only dry run}
        {--confirm= : Must exactly equal ASSIGN LEGACY OWNERSHIP TO COMPANY <id>}';

    protected $description = 'Validate or explicitly assign legacy records to one verified company';

    public function handle(
        LocalDatabaseGuard $databaseGuard,
        LegacyCompanyOwnershipAssigner $assigner
    ): int {
        try {
            $databaseGuard->assertLocal();
        } catch (DomainException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $path = (string) $this->argument('mapping');
        if (! is_file($path) || ! is_readable($path)) {
            $this->error('The mapping file does not exist or is not readable.');

            return self::FAILURE;
        }

        try {
            $manifest = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->error('The mapping file is not valid JSON.');

            return self::FAILURE;
        }

        $expectedKeys = [
            'company_id',
            'operator',
            'records',
            'deferred',
        ];

        if (
            ! is_array($manifest) ||
            array_diff($expectedKeys, array_keys($manifest)) !== [] ||
            array_diff(array_keys($manifest), $expectedKeys) !== [] ||
            ! is_int($manifest['company_id']) ||
            $manifest['company_id'] < 1 ||
            ! is_string($manifest['operator'])
        ) {
            $this->error('The mapping must contain exactly the documented keys, a positive integer company_id, and an operator identifier.');

            return self::FAILURE;
        }

        $companyId = $manifest['company_id'];
        $operator = $manifest['operator'];
        unset($manifest['company_id'], $manifest['operator']);

        if ($this->option('apply') && $this->option('confirm') !== "ASSIGN LEGACY OWNERSHIP TO COMPANY {$companyId}") {
            $this->error('Apply requires the exact company-specific --confirm value. No records were changed.');

            return self::FAILURE;
        }

        try {
            $changes = $assigner->assign(
                $companyId,
                $manifest,
                $operator,
                (bool) $this->option('apply')
            );
        } catch (DomainException|InvalidArgumentException|ModelNotFoundException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info($this->option('apply')
            ? 'Ownership assignment completed.'
            : 'Mapping is valid. Dry run only; no records were changed.');
        foreach ($changes as $table => $counts) {
            $this->line(
                "{$table}: {$counts['assigned']} ".($this->option('apply') ? 'assigned' : 'would be assigned').
                ", {$counts['deferred']} explicitly deferred"
            );
        }

        return self::SUCCESS;
    }
}
