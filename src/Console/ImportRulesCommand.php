<?php

namespace GDI\ApiLogger\Console;

use GDI\ApiLogger\Rules\RuleRepository;
use Illuminate\Console\Command;

class ImportRulesCommand extends Command
{
    protected $signature   = 'apilogger:import-rules';
    protected $description = 'Copy payload/response rules from config/apilogger.php into the api_logger_rules table';

    public function handle(RuleRepository $rules): int
    {
        $created = $rules->importFromConfig();

        $this->info("Imported {$created} rule(s). Existing rules with the same type and pattern were skipped.");

        if (!$rules->databaseEnabled()) {
            $this->warn('Database rules are off: set apilogger.rules.database (APILOGGER_DB_RULES=true) to use them.');
        }

        return self::SUCCESS;
    }
}
