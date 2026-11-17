<?php

namespace App\Console\Commands;

use App\Models\Program;
use App\Models\User;
use App\Services\ProgramImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Imports program packs (JSON) into one user's account as their private ready-made programs.
 * Programs already present for that user (same slug) are skipped, so it is safe to re-run.
 *
 *   php artisan programs:import you@example.com            # all packs from ../data/private
 *   php artisan programs:import you@example.com --path=/some/dir/pack.json
 */
class ImportProgramPacks extends Command
{
    protected $signature = 'programs:import {email : Account that receives the programs}
                            {--path= : A pack file or a directory of packs (default: ../data/private)}';

    protected $description = 'Import program packs into a user account (private, no duplicates)';

    public function handle(ProgramImporter $importer): int
    {
        $user = User::where('email', mb_strtolower($this->argument('email')))->first();
        if (! $user) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        $path = $this->option('path') ?: base_path('../data/private');
        $files = is_dir($path) ? glob(rtrim($path, '/').'/*.json') : [$path];
        if (! $files || ! is_file($files[0])) {
            $this->error("No packs found at {$path}");

            return self::FAILURE;
        }

        foreach ($files as $file) {
            $pack = json_decode((string) file_get_contents($file), true);
            if (! is_array($pack)) {
                $this->warn(basename($file).': not valid JSON, skipped');
                continue;
            }

            // Skip programs this user already has.
            $existing = Program::where('user_id', $user->id)->pluck('slug')->all();
            $pack['programs'] = array_values(array_filter($pack['programs'] ?? [], function ($p) use ($existing, $file) {
                $dup = in_array(Str::slug($p['slug'] ?? ''), $existing, true);
                if ($dup) {
                    $this->line('  = '.($p['name_ru'] ?? $p['name_en']).' (already imported)');
                }

                return ! $dup;
            }));
            if (! $pack['programs']) {
                continue;
            }

            try {
                foreach ($importer->import($pack, $user) as $program) {
                    $this->info('  + '.($program->name_ru ?? $program->name_en));
                }
            } catch (ValidationException $e) {
                $this->error(basename($file).': '.collect($e->errors())->flatten()->first());
            }
        }

        return self::SUCCESS;
    }
}
