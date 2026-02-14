<?php

namespace App\Console\Commands;

use App\Models\MemoryEmbedding;
use Illuminate\Console\Command;

class BackfillEmbeddingMagnitudes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'embeddings:backfill-magnitudes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate and store magnitudes for existing embeddings (performance optimization)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Backfilling embedding magnitudes...');

        $count = MemoryEmbedding::whereNull('magnitude')->count();

        if ($count === 0) {
            $this->info('All embeddings already have magnitudes calculated.');
            return 0;
        }

        $this->info("Found {$count} embeddings without magnitudes.");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        MemoryEmbedding::whereNull('magnitude')->chunk(100, function ($embeddings) use ($bar) {
            foreach ($embeddings as $embedding) {
                $magnitude = MemoryEmbedding::calculateMagnitude($embedding->embedding);
                $embedding->update(['magnitude' => $magnitude]);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info('✅ Successfully backfilled all embedding magnitudes!');

        return 0;
    }
}
